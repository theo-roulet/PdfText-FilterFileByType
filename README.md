## Filter the PDFs to extract text from using their Dublin Core Type     

This plugin enables searching on PDF files by extracting their texts and saving them to their file records, in the  `PDF Text` Metadata.
**This forked version of the PDFText plugin, by the Omeka Developers Team adds customizable conditions to filter the files depending on their metadata `Dublin Core:Type` value.**

* In the plugin config form, a list enumerates all possible values of the files Dublin Core : Type metadata in your Omeka's database.
* All the PDF Files with a dc:type  equal to the checked values in this list will have their text extracted and saved in a `PDF Text` Metadata (just as the original plugin does).
* The text extraction conditions are evaluated everytime a File is edited, so once setup, the plugin will required no manual input.  
* Extracted texts will never be automatically erased or replaced (text metadatas are only created if they do not already exist), but a config option enables to start everything back from scratch.
* This forked version was designed to interact with an Omeka using a SolrSearch Engine. SolrSearchIndex is thus automatically updated with the extracted text when it is first added as metadata (I wasn't able to use a hook and call one plugin from another, but the script now re-save the item to which the file is associated to, and when this happens, SolrSearch updates its index both concerning the item and its file).

## Install

1. Clone or download this repository in the  `plugins/` folder of your omeka
2. Rename this forked plugin to `PdfText/` (instead of `PdfText-FilterFileByType/`)
3. Install, activate and set up the configuration just like any plugin (http:<your_omeka>/admin/plugins/ page)
* *The list of types won't be initialized the first time you open the config page : just save and exit, and when coming back on the config page all the dc:type values given to your files will be there.*

## Todo

Restore translations.
