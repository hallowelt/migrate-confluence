# Behavior of the Tool in Face of Decisions

BlueSpice/MediaWiki and Confluence share a lot of commonalities. However, there are
fundamental architectural differences between the two platforms. The migration tool
needs to make decisions sometimes how to migrate a specific set of data between these
two.

## Abbreviating Overly Long Page Titles

MediaWiki has a [hard limit](https://www.mediawiki.org/wiki/Manual:Page_title_size_limitations)
of 255 bytes per page title. This includes any namespace and parent page as well.

Nesting pages deeply in Confluence runs quickly into this limitation:

```
My_Root_Page/My_First_Child/My_Second_Child
```

already has 43 bytes. The tool works around this limitation by abbreviating the
page title as necessary. It starts with abbreviating root pages and works up through
the chain of parent pages, until it reaches the current page. As soon as the length
slides into the allowed limit, the abbreviation stops.

Abbreviated titles receive a marker `~1` at the end. If several abbreviations collide,
the second one gets a `~2` and so on. An abbreviated title might then look like this:

```
My_Root_Pa~1/My_First~1/My_Second_Child
```

In order to keep the page title itself readable to visitors, the migration tool amends
the page content with a
[`DISPLAYTITLE`](https://www.mediawiki.org/wiki/Help:Magic_words#DISPLAYTITLE)
set to the original page title.

The tool applies some heuristics to keep as much as possible from the original page titles.

## Modification Date of the Main Page

If you set up a new wiki from scratch, it contains but a single page: the main page, with
a time stamp of the wiki creation.

This is all good, until it comes to importing your migration result. Chances are that the
main page of your Confluence export was edited last _before_ the new wiki was created. In
this case the import faces a problem: the main page of the import is _older_ than the main
page of the wiki, even though the latter is most probably only a placeholder.

The tool therefore suppresses the modification date of the most recent version of the main
page. This way the import will pick this version with a modification time of the time of
import and update the main page to the migrated main page.

The old main page is still there. If you find that you needed the old version, check the
history of the main page and restore the appropriate version.

## Author Information

The migration tool can [add author info](./how_to_add_author_info.md) to the export data.
However, there are still some things to consider.

### Page Authors

Page author information is only added if the tool is asked to do so. However, you need to
create user accounts _before_ importing data. Otherwise the revision will be assigned to
the non-functional user account `imported>[Username]`. There is no re-assignment,
if the `Username` account is created later.

Check the helper file `users.xml` in the migration result. It lists all user accounts that
were encountered in the export data.

### Comment Authors

Comment author names are always migrated. However, a similar restriction applies: if the
account was not created before the import, the wiki will show the comment author as
“Anonymous”. Under the hood, the original user is still stored in the DB, though.

As soon as the user account is created, the display of the comment author is updated as well.

## Links to Deleted Pages

Confluence provides several records for the same page title within a space: historical
revisions, drafts, trashed/deleted content, and the one current, live page.

When another page links to a page title, the tool resolves that link only against the
*current* version of the target. If the target page was moved to the Confluence trash
or to another space, and no current version with that title exists anymore, the link is **not**
resolved, even though a historical/trashed row with a matching title is technically still
present in the export.

This is intentional: a link to a deleted page should surface as a broken link (category
`Broken_page_link`, see [how to evaluate the result](./how_to_evaluate_the_result.md)) so
you can notice and fix it, instead of silently pointing to an arbitrary non-current record
that was never actually migrated.

## Category “Pages with syntax highlighting errors”

You might notice this category when checking migration results. This is a feature of the
[SyntaxHighlight extension](https://www.mediawiki.org/wiki/Extension:SyntaxHighlight).
If a `<syntaxhighlight>` element has no programming language set the extension automatically
places the page in this category.

This will happen in migrations, because the language parameter is not required by Confluence.
Therefore the migration tool will create the `<syntaxhighlight>` element, but without
telling the extension, _how_ to highlight the code.

Use this category, if you want to fine-tune your code display. If you used the `code`
macro only as `<pre>` on steroids, you can ignore this feature completely.
