<?php

namespace DNADesign\Elemental\Forms;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Versioned\VersionedGridFieldItemRequest;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\ArrayData;

/**
 * Overrides core Versioned GridField support to provide revert to version
 * support.
 */
class HistoricalVersionedGridFieldItemRequest extends VersionedGridFieldItemRequest
{
    private static $allowed_actions = [
        'view',
        'ItemEditForm'
    ];

    /**
     * The requested version ID
     *
     * @var int
     */
    protected $versionId;

    public function __construct($gridField, $component, $record, $requestHandler, $popupFormName)
    {
        if ($this->versionId = $requestHandler->getRequest()->requestVar('VersionID')) {
            $record = Versioned::get_version(get_class($record), $record->ID, $this->versionId);

            if (!$record) {
                return $requestHandler->httpError(404, _t(__CLASS__.'.InvalidVersion', 'Invalid version'));
            }
        }

        parent::__construct($gridField, $component, $record, $requestHandler, $popupFormName);
    }

    public function view($request)
    {
        if (!$this->record->canView()) {
            $this->httpError(403);
        }

        $controller = $this->getToplevelController();

        $form = $this->ItemEditForm();

        $data = ArrayData::create([
            'Backlink'     => $controller->Link(),
            'ItemEditForm' => $form
        ]);
        $return = $data->renderWith($this->getTemplates());

        if ($request->isAjax()) {
            return $return;
        }

        return $controller->customise(['Content' => $return]);
    }

    public function ItemEditForm()
    {
        $form = parent::ItemEditForm();

        $form->Fields()->push(HiddenField::create('VersionID', '', $this->record->Version));
        $form->Fields()->addFieldToTab(
            'Root.Main',
            ReadonlyField::create('Sort', _t(__CLASS__ .'.Position', 'Position'), $this->record->Sort)
        );

        $fields = $form->Fields()->makeReadonly();
        $fields->unshift($this->getVersionGridField()->setForm($form));

        $form->setFields($fields);

        return $form;
    }

    public function doRollback($data, $form)
    {
        // Check permission
        if (!$this->record->canEdit()) {
            return $this->httpError(403);
        }

        // Save from form data
        $this->record->doRollbackTo($this->record->Version);

        $link = '<a href="' . $this->Link('edit') . '">"'
            . htmlspecialchars($this->record->Title, ENT_QUOTES)
            . '"</a>';

        $message = _t(
            __CLASS__ .'.RolledBack',
            'Rolled back {name} to version {version} {link}',
            array(
                'name' => $this->record->i18n_singular_name(),
                'version' => $this->record->Version,
                'link' => $link
            )
        );

        $form->sessionMessage($message, 'good', ValidationResult::CAST_HTML);
        $controller = $this->getToplevelController();

        return $controller->redirect($this->record->CMSEditLink());
    }

    public function getFormActions()
    {
        $record = $this->getRecord();

        if (!$record || !$record->has_extension(Versioned::class)) {
            return $actions;
        }

        $this->beforeExtending('updateFormActions', function (FieldList $actions) use ($record) {
            if (!$record->isLatestVersion()) {
                $actions->removeByName('action_doUnpublish');
                $actions->removeByName('action_doDelete');
                $actions->removeByName('action_doSave');
                $actions->removeByName('action_doPublish');
                $actions->removeByName('action_doArchive');
            }

            if ($record->canEdit()) {
                $actions->push(
                    FormAction::create(
                        'doRollback',
                        _t(__CLASS__.'.REVERT', 'Revert to this version')
                    )
                        ->setUseButtonTag(true)
                        ->setDescription(_t(
                            __CLASS__.'.BUTTONREVERTDESC',
                            'Publish this record to the draft site'
                        ))
                        ->addExtraClass('btn-warning font-icon-back-in-time')
                );
            }
        });

        $actions = parent::getFormActions();

        return $actions;
    }

    /**
     * Get the specific version in a GridField, as the only record
     *
     * @return GridField
     */
    public function getVersionGridField()
    {
        /** @var GridField $versionGridField */
        $versionGridField = $this->getRecord()->getHistoryFields(false)->fieldByName('History');

        /** @var GridFieldConfig */
        $config = $versionGridField->getConfig();

        $config->removeComponentsByType([
            GridFieldPaginator::class,
            ElementalGridFieldHistoryButton::class,
        ]);

        // Filter this version ID
        $versionGridField->setList(
            $versionGridField->getList()->filter(['Version' => $this->versionId])
        );

        // Add a unique class name so we can style
        $versionGridField->addExtraClass('elemental-block__history--detail');

        return $versionGridField;
    }
}
