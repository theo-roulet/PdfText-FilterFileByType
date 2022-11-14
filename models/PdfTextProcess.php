<?php
/**
 * PDF Text
 *
 * @copyright Copyright 2007-2012 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * @package Omeka\Plugins\PdfText
 */
class PdfTextProcess extends Omeka_Job_AbstractJob
{
    /**
     * Process all PDF files in Omeka.
     */
    public function perform()
    {
        $RequiredTypes = json_decode(get_option('pdf_text_required_types'), true)["RequiredFileTypes"];
        // $RequiredTypes = json_decode(get_option('pdf_text_required_types'), true)->RequiredFileTypes;
        // $RequiredTypes = ["type_test"];

        $pdfTextPlugin = new PdfTextPlugin;
        $fileTable = $this->_db->getTable('File');

        $select = $this->_db->select('f.id')
            ->from(array('f' => $this->_db->File))
            ->where('mime_type IN (?)', $pdfTextPlugin->getPdfMimeTypes());

        // Iterate all PDF file records.
        $pageNumber = 1;
        while ($files = $fileTable->fetchObjects($select->limitPage($pageNumber, 50))) {
            foreach ($files as $file) {
                if (array_intersect($RequiredTypes, $file->getElementTexts("Dublin Core", "Type"))) {

                    // Delete any existing PDF text element texts from the file.
                    $textElement = $file->getElement(
                        PdfTextPlugin::ELEMENT_SET_NAME,
                        PdfTextPlugin::ELEMENT_NAME
                    );
                    $file->deleteElementTextsByElementId(array($textElement->id));

                    // Extract the PDF text and add it to the file.
                    $filepath = FILES_DIR . '/original/' . $file->filename;
                    $text = $pdfTextPlugin->pdfToText($filepath);
                    if (isset($text)) {
                        $file->addTextForElement($textElement, $text);
                        $file->save();
                        // Gets file's item and save it, just to refresh the SOLR Index (the SolrSearch Plugin reacts to modifications on the item, including modifications on the file)
                        $item = get_record_by_id('item', $file->item_id);
                        $item->save();
                    }
                }
                release_object($file);
            }
            $pageNumber++;
        }
    }
}
