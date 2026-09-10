# Confluence Migration Tool

Welcome to the documentation! The aim of this tool is to convert Confluence spaces into
MediaWiki structures as losslessly as possible.

## Contributing

We welcome any contribution!

This project is actively developed. To prevent frustrating double or stale work,
please open an issue first with the intended changes that you’d like to provide.

## Preparation

The tool works on XML exports of one or more Confluence spaces. We suggest one separate XML export for each space.

It is neither necessary nor recommended to run the tool on the same server as your MediaWiki installation. Since some steps of the process need a good amount of memory and storage, we suggest to run the migration on a separate piece of hardware, e.g., your local computer, and copy the resulting import files to your web server afterwards.

### Export Spaces from Confluence

**Step 1:** Visit the space settings page. Choose “Content Tools” → “Export” (Data Center) or “General” → ”Export space” (Cloud). Choose “XML” as export format. This option may be hidden behind the ”Advanced Options” toggle.

![Export 1][c001]

**Step 2:** Export options: Choose “Custom Export” (Data Center) or “Select what to export” (Cloud) and check all boxes, including ”Include comments”. You can deselect pages that you do not want to migrate.

![Export 2][c002]

**Step 3:** Click the “Export” button and wait for the small “Download here” link to appear. Download the zip archive with the export data.

![Export 3][c003]

### Prepare the Data

For the remainder of this documentation we assume to work from within a folder `/tmp/confluence`
on your machine that is reachable for the migration tool.

**Step 1:**
Save the zip file as `Confluence-export.zip` to the folder.

**Step 2:**
Create the input directory `/tmp/confluence/input` and unzip the content

```
cd /tmp/confluence
mkdir input
unzip Confluence-export.zip -d input
```

 _Alternatively:_ If you want to handle several exports at the same time, create a sub-folder for each export in the `input` folder. The tool will recursively search for all `entities.xml` files in these folders.

The folder should now contain the files `entities.xml` and `exportDescriptor.properties` as well as the folder `attachments`.

You are now ready to start the migration process.

[c001]: images/Confluence_export_space_001.png
[c002]: images/Confluence_export_space_002.png
[c003]: images/Confluence_export_space_003.png

## Usage

[Pandoc](https://pandoc.org/) must be installed on your system.

Migration is then a four-step process:

1. Analyze the import data and read the `entities.xml` file of the Confluence input folder. Store the data into `workspace.sqlite` in the Wiki output folder.
2. Extract data from one place to another, so that the next steps can seemlessly run.
3. Convert the pages one by one from Confluence-flavored HTML to Wiki text. This step runs Pandoc.
4. Compose the converted data into XML files that MediaWiki can easily import.

## Configuration

You can control features of the migration by providing a configuration file.
See [`doc/configuration.md`](./configuration.md) for details.