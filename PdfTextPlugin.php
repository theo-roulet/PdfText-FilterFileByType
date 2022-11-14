<?php
/**
 * PDF Text
 *
 * @copyright Copyright 2007-2012 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * The PDF Text plugin.
 *
 * @package Omeka\Plugins\PdfText
 */
class PdfTextPlugin extends Omeka_Plugin_AbstractPlugin
{
    const ELEMENT_SET_NAME = 'PDF Text';
    const ELEMENT_NAME = 'Text';

    protected $_hooks = array(
        'install',
        'uninstall',
        'initialize',
        'config_form',
        'config',
        'before_save_file',
        'after_save_file',

    );

    protected $_pdfMimeTypes = array(
        'application/pdf',
        'application/x-pdf',
        'application/acrobat',
        'text/x-pdf',
        'text/pdf',
        'applications/vnd.pdf',
    );

    // protected $_filters = array(    'admin_navigation_main'   ); I miserably failed to create a plugin controller and with it an admin nav config tab ...


    /**
     * Install the plugin.
     */
    public function hookInstall()
    {

        // Don't install if the pdftotext command doesn't exist.
        // See: http://stackoverflow.com/questions/592620/check-if-a-program-exists-from-a-bash-script
        if ((int) shell_exec('hash pdftotext 2>&- || echo 1')) {
            throw new Omeka_Plugin_Installer_Exception(__('The pdftotext command-line utility '
            . 'is not installed. pdftotext must be installed to install this plugin.'));
        }
        // Don't install if a PDF element set already exists.
        if ($this->_db->getTable('ElementSet')->findByName(self::ELEMENT_SET_NAME)) {
            throw new Omeka_Plugin_Installer_Exception(__('An element set by the name "%s" already '
            . 'exists. You must delete that element set to install this plugin.', self::ELEMENT_SET_NAME));
        }
        // Adding a default array to store options containing the list of dc:Type File Types to ignore / process  (will be set empty to initialize the config)
        $defaults = [
                      'RequiredFileTypes' => []
                    ];
        // the default options array is changed in JSON and saved in the omeka_option table
        set_option('pdf_text_required_types', json_encode([$defaults]));
        //
        insert_element_set(
            array('name' => self::ELEMENT_SET_NAME, 'record_type' => 'File'),
            array(array('name' => self::ELEMENT_NAME))
        );
    }

    /**
     * Uninstall the plugin
     */
    public function hookUninstall()
    {
        // Delete the PDF element set.
        $this->_db->getTable('ElementSet')->findByName(self::ELEMENT_SET_NAME)->delete();
        // Delete the options array containing the list of dc:Type File Types to ignore / process
        delete_option('pdf_text_required_types');
    }

    /**
     * Initialize this plugin.
     */
    public function hookInitialize()
    {
        // ADDED : need to parse the option, even if empty, when initalizing
        // And stores it into the _settings property.
        $this->_settings = json_decode(get_option('pdf_text_required_types'), true);
        //
        // Add translation.
        add_translation_source(dirname(__FILE__) . '/languages');
    }

    /**
     * Display the config form.
     */
    public function hookConfigForm()
    {

        // retrieves the options stored in the _settings property
        $settings = $this->_settings;
        $RequiredTypes = json_decode(get_option('pdf_text_required_types'), true)->RequiredFileTypes;


        echo get_view()->partial(
            'plugins/pdf-text-config-form.php',
            ['valid_storage_adapter' => $this->isValidStorageAdapter(),
            'settings' => $settings,         // this passes the options to the partial view
            'rq' => $RequiredTypes
          ],
        );
    }

    /**
     * Handle the config form.
     */
    public function hookConfig($args)
    {
        $post = $args['post'];
        $settings = array('RequiredFileTypes' => isset($post['RequiredFileTypes']) // Check if options were configured on the form
          ? $post['RequiredFileTypes'] // if set, returns the configuration values (list of selected / ignored types)
          : array() // if not set, returns empty array
        );

        set_option('pdf_text_required_types', json_encode($settings)); // that gets converted into JSON and saved in the table omeka_option

        // Run the text extraction process if directed to do so.
        if ($_POST['pdf_text_process'] && $this->isValidStorageAdapter()) {
            Zend_Registry::get('bootstrap')->getResource('jobs')
                ->sendLongRunning('PdfTextProcess');
        }
    }

    /**
     * Add the PDF text to the file record.
     *
     * This has a secondary effect of including the text in the search index.
     * THIS WAS REPLACED BY THE FOLLOWING HOOK :
     */
    public function hookBeforeSaveFile($args)
    {
        //     // // Extract text only on file insert.
    //     // if (!$args['insert']) {
    //     //     return;
    //     // }
    //     // $file = $args['record'];
    //     // // Ignore non-PDF files.
    //     // if (!in_array($file->mime_type, $this->_pdfMimeTypes)) {
    //     //     return;
    //     // }
    //     //
    //     // // Ignore non-PDF files.
    //     // if (!in_array($file->metadata($file, array('Dublin Core', 'Type')), json_decode(get_option('pdf_text_required_types'), true)->RequiredFileTypes)) {
    //     //     return;
    //     // }
    //     //
    //     // // Add the PDF text to the file record.
    //     // $element = $file->getElement(self::ELEMENT_SET_NAME, self::ELEMENT_NAME);
    //     // $text = $this->pdfToText($file->getPath());
    //     // // pdftotext must return a string to be saved to the element_texts table.
    //     // if (is_string($text)) {
    //     //     $file->addTextForElement($element, $text);
    //     // }
    }

    /**
     * Add the PDF text to the file record.
     * 1 - Checks if File meets dc:typê conditions
     * 2 - If so Extracts text and add it as pdf text metadata
     * 3 - Re-saves the File, and resaves the linked item, so that SOLR Index gets updated with it. Dunno what happens with the base search engine.
     */

    public function hookAfterSaveFile($args)
    {
        $RequiredTypes = json_decode(get_option('pdf_text_required_types'), true)["RequiredFileTypes"];
        $file = $args['record'];
        // Ignore non-PDF files.
        if (!in_array($file->mime_type, $this->_pdfMimeTypes)) {
            return;
        }
        // Ignore PDFs, the types of which are not in the parameters list.
        if (!array_intersect($RequiredTypes, $file->getElementTexts("Dublin Core", "Type"))) {
            return;
        }

        // finally, also ignore files which already have some extracted text (we don't want to overwrite existing files.)
        if (!$file->getElementTexts(PdfTextPlugin::ELEMENT_SET_NAME, PdfTextPlugin::ELEMENT_NAME)[0]->text) {
            // Add the PDF text to the file record.
            $element = $file->getElement(self::ELEMENT_SET_NAME, self::ELEMENT_NAME);
            $filepath = FILES_DIR . '/original/' . $file->filename;
            $text = $this->pdfToText($filepath);

            // pdftotext must return a string to be saved to the element_texts table.
            if (is_string($text)) {
                $file->addTextForElement($element, $text);
                $file->save();
                // Gets file's item and save it, just to refresh the SOLR Index (the SolrSearch Plugin reacts to modifications on the item, including modifications on the file)
                $item = get_record_by_id('item', $file->item_id);
                $item->save();
            }
        }
        release_object($file);
        release_object($item);
    }


    /**
     * Extract the text from a PDF file.
     *
     * @param string $path
     * @return string
     */
    public function pdfToText($path)
    {
        $path = escapeshellarg($path);
        return shell_exec("pdftotext -enc UTF-8 $path -");
    }

    /**
     * Determine if the plugin supports the storage adapter.
     *
     * pdftotext cannot be used on remote files, so only support the default
     * Filesystem adapter, which stores files locally.
     *
     * @return bool
     */
    public function isValidStorageAdapter()
    {
        $storageAdapter = Zend_Registry::get('bootstrap')
            ->getResource('storage')->getAdapter();
        if (!($storageAdapter instanceof Omeka_Storage_Adapter_Filesystem)) {
            return false;
        }
        return true;
    }

    /**
     * Get the PDF MIME types.
     *
     * @return array
     */
    public function getPdfMimeTypes()
    {
        return $this->_pdfMimeTypes;
    }
}
