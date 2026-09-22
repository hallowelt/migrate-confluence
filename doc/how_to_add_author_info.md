# Add Revision Author Info

> **Attention:** Complete user info is only provided in Confluence exports,
> if they were made by an administrative account. Otherwise it will contain
> placeholders instead of the real user names. Make sure to create the
> exports with such an elevated account!

By default, page and blog post revisions in the generated XML do not carry
author information. Enable it with the config option

```yaml
config:
    add-userinfo: true
```

This adds the corresponding user’s name to each page/blog post revision.

> **Note:** The user must already exist in the target wiki prior to the import.
> Otherwise MediaWiki will create an `Imported>[USERNAME]` dummy user that
> will _not_ be mapped to any newly created user later.

If user names changed between the old Confluence accounts and the target
wiki, provide a mapping via the `analyze` step's `--usermap` option:

```
analyze --src=... --dest=... --usermap=/path/to/usermap.csv
```

The CSV file has three columns. A header starting with `confluence-userkey` is
recognized and skipped.

```csv
confluence-userkey,confluence-username,mediawiki-username
f09b6c77-6860-4efe-a589-adcc002dfb3f,jdoe,John.Doe
a04f3bc4-7999-4a59-bac5-d395f97e14d2,old.login@example.org,Jane_Doe
```

The mapping is only read when `add-userinfo` is enabled. Column 1
is the internal Confluence user key, column 2
is the Confluence username (the `name`/`lowerName` property of the
user object), column 3 is the MediaWiki username to use
instead of the automatically derived one. Users without a matching row keep
the automatically derived username. All other user data is read from the
Confluence export data.

## Creating a Pre-Filled CSV File

Use the helper command `printusers` to generate a pre-filled list of user
accounts from your export data:

```
docker run --rm -v $(pwd):/data bluespice/migrate-confluence:latest printusers --src=/data/input --dest=/data/usermap.csv
```
