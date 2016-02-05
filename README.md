# Day-One-XML-to-Markdown-Converter
PHP script to convert DayOne entries to separate .md files

I wanted an export option for DayOne that saved the entries to individual files. I didn't want to export each entry one at a time. I used this script to convert all the DayOne XML data files to Markdown. I left in some of my debugging code. Feel free to use this to build your own converter.

 Notes:
 * 	- this only works for the original DayOne app which stored entries as xml files
 * 	- extension of entries must be changed from .doentry to .xml, use cli or automater
 * 	- Destination folder  must have write permissions
