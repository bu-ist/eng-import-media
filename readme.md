# ENG Mediafix
A collection of commands to scan for media library links (both anchor and img) and to fix links by downloading the referenced media and rewriting the links to point to the newly imported file.  This is useful when post content has been imported, but not attachments.

## Scanning

Mediafix can scan post content and report on the status of linked media.  There are 2 types of scans available, one for anchor tags linking to items from the media library, and one for image tags.

Scans can report results in a table directly to the console, or can be sent to a tab-delimited text file for further analysis.

### Anchor tags linking to the media library

`wp mediafix report-library-a`

### IMG tags

`wp mediafix report-img`

#### Report to file

When creating a tab-delimited file, send stderr and stdout to different files for clean output that can be imported to other programs for analysis.

```
wp mediafix report-library-a 2>report-error.txt 1>report-library-a.tab

wp mediafix report-img 2>report-error.txt 1>report-img.tab
```

## Fixing

Mediafix has 3 fix commands to handle different markup cases.  The attachment command handles the case of 2 related tags (an anchor tag enclosing an img tag) and should be run first. The img and anchor commands handle individual tags and can be run anytime after the attachment command.

### Attachments

`wp mediafix fix-all-attachments`

This handles both the enclosing anchor tag pointing to the full resolution media attachment and the enclosed img pointing to the downsampled image.

### IMG tags

`wp mediafix fix-all-img`

This will handle all of the remaining img tags that weren’t part of an attachment.

### Anchor tags

`wp mediafix fix-all-a-href`

This will handle library based anchor tags.

### Fix single posts

In addition to the fix-all commands, each has an analog command that takes a single post id to operate on.  This can be useful to troubleshoot or test on a single post. The command names are:

```
fix-one-attachments
fix-one-img
fix-one-a-href
```

### Fix IMG Thumbnails

This command scans for scaled library items that aren't already rendered at the correct size, and creates the correct size to match the specified img src.

`wp mediafix fix-all-thumbnails`

### Categorize

This command can use the hidden post_meta value containing the source url of attachments to assign a taxonomy term.  Use the basic command to scan for attachments with the correct post_meta key:

`wp mediafix report-import-dept`

Add the `update` subcommand to actually assign the detected terms.

`wp mediafix report-import-dept update`
