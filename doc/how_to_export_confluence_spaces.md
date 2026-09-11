# Exporting Spaces from Confluence

The tool works on XML exports of one or more Confluence spaces. We suggest one
separate XML export for each space.

## In Your Confluence Installation

**Step 1:** Visit the space settings page. Choose “Content Tools” → “Export” (Data Center) or “General” → ”Export space” (Cloud). Choose “XML” as export format. This option may be hidden behind the ”Advanced Options” toggle.

![Screenshot: admin page “Space Settings”][c001]

**Step 2:** Export options: Choose “Custom Export” (Data Center) or “Select what to export” (Cloud) and check all boxes, including ”Include comments”. You can deselect pages that you do not want to migrate.

![Screenshot of Confluence page: export options][c002]

**Step 3:** Click the “Export” button and wait for the small “Download here” link to appear. Download the zip archive with the export data.

![Screenshot of Confluence page: export complete][c003]

### User Account Names

If you need user account names to stay intact, please make sure that you make the export as
an administrator with all rights. Otherwise the export will not contain user names and e-mails
but generated hash values instead.

## Prepare the Data

For the remainder of this documentation we assume to work from within a folder `/tmp/confluence`
on your machine that is reachable for the migration tool.

**Step 1:**
Save the zip file as `Confluence-export.zip` to the folder.

**Step 2:**
Create the input directory `/tmp/confluence/input` and unzip the content

```bash
cd /tmp/confluence
mkdir input
unzip Confluence-export.zip -d input
```

> _Alternatively:_ If you want to handle several exports at the same time, create
> a sub-folder for each export in the `input` folder. The tool will recursively
> search for all `entities.xml` files in these folders.

The folder should now contain the files `entities.xml` and
`exportDescriptor.properties` as well as the folder `attachments`:

```bash
$ find /tmp/confluence/input -maxdepth 1
/tmp/confluence/input/
/tmp/confluence/input/attachments/
/tmp/confluence/input/entities.xml
/tmp/confluence/input/exportDescriptor.properties
```

or in the case of several exports:

```bash
$ find /tmp/confluence/input -maxdepth 2
/tmp/confluence/input/
/tmp/confluence/input/A/
/tmp/confluence/input/A/attachments/
/tmp/confluence/input/A/entities.xml
/tmp/confluence/input/A/exportDescriptor.properties
/tmp/confluence/input/B/
/tmp/confluence/input/B/attachments/
/tmp/confluence/input/B/entities.xml
/tmp/confluence/input/B/exportDescriptor.properties
/tmp/confluence/input/C/
/tmp/confluence/input/C/attachments/
/tmp/confluence/input/C/entities.xml
/tmp/confluence/input/C/exportDescriptor.properties
```

You are now ready to [start the migration process](./usage.md).

[c001]: images/Confluence_export_space_001.png
[c002]: images/Confluence_export_space_002.png
[c003]: images/Confluence_export_space_003.png