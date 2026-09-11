# Usage

[Pandoc](https://pandoc.org/) must be installed on your system. If you use the
Docker image, this requirement is automatically fulfilled.

> **Note:**
> It is neither necessary nor recommended to run the tool on the same server as
> your MediaWiki installation. Since some steps of the process need a good amount
> of memory and storage, we suggest to run the migration on a separate piece of
> hardware, e.g., your local computer, and copy the resulting import files to your
> web server afterwards.

Migration is a four-step process:

1. Analyze the import data and read the `entities.xml` file of the Confluence input folder. Store the data into `workspace.sqlite` in the Wiki output folder.
2. Extract data from one place to another, so that the next steps can seemlessly run.
3. Convert the pages one by one from Confluence-flavored HTML to Wiki text. This step runs Pandoc.
4. Compose the converted data into XML files that MediaWiki can easily import.

For this documentation we assume an XML export in `/tmp/confluence/input` as suggested in
[the export guide](./how_to_export_confluence_spaces.md).

## Migrate the contents

1. Create the "workspace" directory for the processed data:
    ```bash
    mkdir /tmp/confluence/workspace
    ```
2. From the main directory (e.g. `/tmp/confluence`), run the migration commands
	1. Run

		```bash
		docker run --rm -v $(pwd):/data bluespice/migrate-confluence:latest analyze --src=/data/input --dest=/data/workspace
		```

		to analyze and read the exported data. This creates an SQLite database `workspace/workspace.sqlite`, where you can check and, if needed, post-process the data before running the next steps.
	2. Run

	    ```bash
		docker run --rm -v $(pwd):/data bluespice/migrate-confluence:latest extract --src=/data/input --dest=/data/workspace
		```

		to prepare all contents, like page contents, attachments and images for the conversion step. In this step the future wiki titles of pages, blog posts and attachments are created.
	3. Check database tables `logging`, `page_invalid_titles`, `blog_post_invalid_titles`, `page_template_invalid_titles` and `attachment_invalid_titles`. Modifiy titles if necessary.
	4. Run

	    ```bash
		docker run --rm -v $(pwd):/data bluespice/migrate-confluence:latest convert --src=/data/workspace --dest=/data/workspace
		```

		(yes, `--src=/data/workspace/` ) to convert the wikipage contents from Confluence Storage XML to MediaWiki WikiText. For large spaces, see [Parallel convert](#parallel-convert) below.
	5. Check database tables `logging`, `body_contents`, `page_template_contents`
	5. Run

		```bash
		docker run --rm -v $(pwd):/data bluespice/migrate-confluence:latest compose --src=/data/workspace --dest=/data/workspace
		```

		(yes, `--src=/data/workspace/` ) to create importable data
	6. Check the log files in workspace directory for errors, especially the `skipped_pages.log`. Pages logged in this file are not part of the mediawiki import data.

Important: If you re-run the scripts you will need to clean up the "workspace" directory!

The folder `/tmp/confluence/workspace/results` contains the finished migrated data ready for
[import into MediaWiki](./how_to_import_the_result.md).

## Configuration

You can control features of the migration by providing a configuration file.
See [`doc/configuration.md`](./configuration.md) for details.

If the configuration file is placed in, e.g., `/tmp/confluence/config.yaml`, it
can be applied by adding the option `--config=/data/config.yaml` to the commands
above.

## Parallel convert

For large Confluence spaces the `convert` step can be slow. You can speed it up by running multiple worker processes in parallel using the `--workers` option.

```bash
docker run --rm -v $(pwd):/data bluespice/migrate-confluence:latest convert \
  --src=/data/workspace --dest=/data/workspace \
  --workers=4
```

The command spawns the requested number of child processes automatically. Each worker handles a disjoint slice of the file list, so every file is converted exactly once. Progress lines are prefixed with `[Worker N]` so you can follow each process individually. If any worker fails the command exits with a non-zero status and reports which workers were affected.

Choose `--workers` based on the number of available CPU cores. A value between 2 and 8 is typical; there is no benefit in exceeding the number of cores on your machine.

> **Note:** `--workers=1` (the default) behaves identically to running without
> the option — no child processes are spawned.

## User spaces

In confluence user spaces are protected. In MediaWiki this is not possible for
namespace `User`. Therefore user spaces are migrated to a namespace
`User<username>` which can be protected in `BlueSpice for MediaWiki`.