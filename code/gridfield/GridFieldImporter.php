<?php

/**
 * Adds a way to import data to the GridField's DataList
 */
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Forms\GridField\GridField_URLHandler;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;

class GridFieldImporter implements GridField_HTMLProvider, GridField_URLHandler
{

    /**
     * Fragment to write the button to
     * @var string
     */
    protected $targetFragment;

    /**
     * The BulkLoader to load with
     * @var string
     */
    protected $loader = null;

    /**
     * Can the user clear records
     * @var boolean
     */
    protected $canClearData = true;

    public function __construct($targetFragment = "after")
    {
        $this->targetFragment = $targetFragment;
    }

    /**
     * Set the bulk loader for this importer
     * @param BetterBulkLoader $loader
     * @return GridFieldImporter
     */
    public function setLoader(BetterBulkLoader $loader)
    {
        $this->loader = $loader;

        return $this;
    }

    /**
     * Get the BulkLoader
     * @return BetterBulkLoader
     */
    public function getLoader(GridField $gridField)
    {
        if (!$this->loader) {
            $this->loader = $this->scaffoldLoader($gridField);
        }

        return $this->loader;
    }

    /**
     * Scaffold a bulk loader, if none is provided
     */
    public function scaffoldLoader(GridField $gridField)
    {
        $gridlist = $gridField->getList();
        $class = ($gridlist instanceof HasManyList) ?
                "ListBulkLoader" : "BetterBulkLoader";
        //set the correct constructor argument
        $arg = ($class === "ListBulkLoader" ||
            is_subclass_of($class, "ListBulkLoader")) ?
                $gridlist : $gridField->getModelClass();
        $loader = new $class($arg);
        $loader->setSource(new CsvBulkLoaderSource());

        return $loader;
    }

    /**
     * @param boolean $canClearData
     */
    public function setCanClearData($canClearData = true)
    {
        $this->canClearData = $canClearData;
    }

    /**
     * Get can clear data flag
     */
    public function getCanClearData()
    {
        return $this->canClearData;
    }

    /**
     * Get the html/css button and upload field to perform import.
     */
    public function getHTMLFragments($gridField)
    {
        $button = new GridField_FormAction(
            $gridField,
            'import',
            _t('TableListField.CSVIMPORT', 'Import from CSV'),
            'import',
            null
        );
        $button->setAttribute('data-icon', 'drive-upload');
        $button->addExtraClass('no-ajax');
        $uploadfield = $this->getUploadField($gridField);
        $data = array(
            'Button' => $button,
            'UploadField' => $uploadfield
        );
        $importerHTML = ArrayData::create($data)
                    ->renderWith("GridFieldImporter");
        Requirements::javascript('importexport/javascript/GridFieldImporter.js');

        return array(
            $this->targetFragment => $importerHTML
        );
    }

    /**
     * Return a configured UploadField instance
     *
     * @param  GridField $gridField Current GridField
     * @return UploadField          Configured UploadField instance
     */
    public function getUploadField(GridField $gridField)
    {
        $uploadField = UploadField::create(
                $gridField->Name."_ImportUploadField", 'Upload CSV'
        )   ->setForm($gridField->getForm())
            ->setAllowedMaxFileNumber(1)
            ->setAllowedExtensions(array('csv'))
            ->setFolderName('csvImports') //TODO: don't store temp CSV in assets
            ->addExtraClass("import-upload-csv-field");

        return $uploadField;
    }

    public function getActions($gridField)
    {
        return array('importer');
    }

    public function getURLHandlers($gridField)
    {
        return array(
            'importer' => 'handleImporter'
        );
    }

    /**
     * Pass importer requests to a new GridFieldImporter_Request
     */
    public function handleImporter($gridField, $request = null)
    {
        $controller = $gridField->getForm()->getController();
        $handler    = new GridFieldImporter_Request($gridField, $this, $controller);

        return $handler->handleRequest($request, DataModel::inst());
    }
}
