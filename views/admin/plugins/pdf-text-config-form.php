<div class="field">
    <div class = "six columns omega">
      <p>This plugin enables searching on PDF files by extracting their texts and saving them to their file records, in the  <span style='background-color:rgba(200, 200, 200, 0.5);'>PDF Text</span> Metadata </p>
      <p> This forked version adds customizable conditions to filter the files depending on their metadata <span style='background-color:rgba(200, 200, 200, 0.5);'>Dublin Core:Type</span> value.

      <h3>Filter PDF Text extraction depending on their Dublin Core Types </h3>

      <ul>
        <li><b>The list below enables you to filter the PDF Files, extracting only the text from those having a Dublin Core Type Metadata listed below</b> (the list simply enumerates all possible values in your Omeka's database ... If you're looking for a closed list, consider using the Simple Vocab Plugin).</li>
        <li><b>Note that typeless PDFs cannot be selected : to be treated, files MUST have a Type specified.</b></li>
        <li>The text extraction conditions will be checked after any File Save, but will be carried on only on files not yet having any PDF:text value : <b>  no extracted text will never be automaticallty erased or replaced by this plugin </b>
          you can correct and edit your texts without concern.</li>
      </ul>

      <h3>Changing File Type and Deleting Text</h3>
        <ul>
          <li>Changing a PDF Type will automatically extract its text if this File has no PDF:Text Metadata value.</li>
          <li>Changing a PDF Type won't have any effect if this text already have PDF:Text Metadata value.</li>
          <li>To delete or restart one text extraction from scratch, manually delete the PDF:Text Metadata value and the text will be automatically reprocessed when the file is saved. </li>
        </ul>
    </div>

    <!-- FRENCH VERSION -->
      <!--
       <p>Ce plugin permet d'extraire automatiquement le contenu textuel des PDF et de le sauvegarder dans la notice descriptive du fichier (propriété <span style='background-color:rgba(200, 200, 200, 0.5);'>PDF Text</span>).<p>
      <p><i>Ce fork ajoute au plugin d'origine la possibilité de traiter différement les PDF en fonction de leur type : la métadonnée  attribuée au fichier dans Omeka.</i></p>
      <h3>Filtrer l'extraction du texte par type de fichier </h3>
      <ul>
        <li><b>Seuls les fichiers PDF dont les types sont sélectionnés ci-dessous sont traités </b> Les PDF ciblés doivent donc impérativement avoir une métadonnée <span style='background-color:rgba(200, 200, 200, 0.5);'>Dublin Core:Type</span>.</li>
        <li>Le texte est extrait automatiquement après la sauvegarde d'un PDF.</li>
        <li>Une fois extrait le texte ne sera jamais effacé automatiquement par ce plugin.</li>
      </ul>
      <h3>Changer le type d'un fichier PDF existant :</h3>
        <ul>
          <li>Extraira automatiquement le texte du PDF si le fichier n'avait pas encore été traité.</li>
          <li>Ne supprimera pas / ne remplacera pas le texte déjà extrait (pour cela il faut supprimer manuellement la métadonnée PDF text à travers l'interface administrateur.)</li>
        </ul> -->

    <?php  // Selects all existing values for DublinCore:types given to Files :
         $allFileTypes = [];
         $db = get_db();
         $fileTable = $db->getTable('File')
                         ->findBy(array('dublin_core_type' => array('*')));
         foreach ($fileTable as $file) {
             $type = metadata($file, array('Dublin Core', 'Type'));
             array_push($allFileTypes, $type) ;
         }
         $FileTypesList = array_unique($allFileTypes);
    ?>
    <div class="eight columns omega">

    <div class="inputs four columns omega" style="float:left;">
      <h2> <?php echo __("Selected Types for File text extraction :"); ?></h2>
        <?php // echoing form
              foreach ($FileTypesList as $type) {
                  if ($type != null) {
                      echo $this
                        ->formCheckbox(
                            'RequiredFileTypes[]', //Form field group Name
                            $type, // This form Groupd Field Name
                            ['enableOcr' => true,  // enabled option if selected (checked)
                                         'checked' => in_array($type, $settings['RequiredFileTypes']) // fetches the enabled / disabled state of the options in the database general option table,  (options are stored as a JSON object)
                            ]
                        );
                      // if ($type == null) {   echo "<i>Fichiers sans type</i><br>";  } else { } ... // Enabling the selection and handling of Null values will be tedious
                      echo $type . '<br>' ;
                  }
              }
              echo "<br><i>This list simply enumerates all possible values in your Omeka's database.</i>";
        ?>
    </div>

    <div class="inputs four columns omega" style ="float:right;">
        <?php if ($this->valid_storage_adapter):
          // A reworked version of the reset command
           ?>
            <h2>
            <?php echo "Erase and reextract all texts";
                  echo $this->formCheckbox('pdf_text_process'); ?>
            </h2>
            <?= "<h4>Reset all extracted texts. Filters by type will still apply. Runs as a background job and can be long, depending on the number of PDF stored in your Omeka.</h4>";  ?>
            <?php else: ?>
            <p class="error">
            <?php
            echo __(
                      'This plugin does not support processing of PDF files that '
                . 'are stored remotely. Processing existing PDF files has been '
                . 'disabled.'
                  );
            ?>
            </p>
        <?php endif; ?>
    </div>
  </div>
</div>
