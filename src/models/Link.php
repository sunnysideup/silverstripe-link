<?php

namespace gorriecoe\Link\Models;

use InvalidArgumentException;
use SilverStripe\Assets\File;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Core\Convert;
use UncleCheese\DisplayLogic\Forms\Wrapper;

/**
 * Link
 *
 * @package silverstripe
 * @subpackage silverstripe-link
 */
class Link extends DataObject
{
    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'Link';

    /**
     * Database fields
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar(255)',
        'Type' => 'Varchar',
        'URL' => 'Varchar(255)',
        'Email' => 'Varchar(255)',
        'Phone' => 'Varchar(255)',
        'OpenInNewWindow' => 'Boolean',
        'Template' => 'Varchar(255)'
    ];

    /**
     * Has_one relationship
     * @var array
     */
    private static $has_one = [
        'File' => File::class,
    ];

    /**
     * Defines summary fields commonly used in table columns
     * as a quick overview of the data for this dataobject
     * @var array
     */
    private static $summary_fields = [
        'Title' => 'Title',
        'LinkType' => 'Type',
        'LinkURL' => 'Link'
    ];

    /**
     * A map of styles that are available in the cms for
     * users to select from.
     *
     * @var array
     */
    private static $styles = [];

    /**
     * A map of object types that can be linked to
     * Custom dataobjects can be added to this
     *
     * @var array
     */
    private static $types = [
        'URL' => 'URL',
        'Email' => 'Email address',
        'Phone' => 'Phone number',
        'File' => 'File on this website',
    ];

    /**
     * List the allowed included link types.  If null all are allowed.
     *
     * @var array
     */
    private static $allowed_types = null;

    /**
     * Ensures that the methods are wrapped in the correct type and
     * values are safely escaped while rendering in the template.
     * @var array
     */
    private static $casting = [
        'ClassAttr' => 'HTMLFragment',
        'TargetAttr' => 'HTMLFragment',
        'IDAttr' => 'HTMLFragment'
    ];

    /**
     * @var string custom CSS classes for template
     */
    protected $classes = [];

    /**
     * @var string custom style for template
     */
    protected $style;

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = FieldList::create(
            TabSet::create(
                "Root",
                Tab::create("Main")
            )
            ->setTitle(_t('SiteTree.TABMAIN', "Main"))
        );

        if ($styles = $this->i18nStyles) {
            $fields->addFieldToTab(
                'Root.Main',
                DropdownField::create(
                    'Style',
                    _t(__CLASS__ . '.STYLE', 'Style'),
                    $styles
                )
                ->setEmptyString('Default')
            );
        }

        $fields->addFieldsToTab(
            'Root.Main',
            [
                TextField::create(
                    'Title',
                    _t(__CLASS__ . '.TITLE', 'Title')
                )
                ->setDescription(_t(__CLASS__ . '.OPTIONALTITLE', 'Optional. Will be auto-generated from link if left blank.')),
                OptionsetField::create(
                    'Type',
                    _t(__CLASS__ . '.LINKTYPE', 'Type'),
                    $this->i18nTypes
                )
                ->setValue('URL'),
                Wrapper::create(
                    TreeDropdownField::create(
                        'FileID',
                        _t(__CLASS__ . '.FILE', 'File'),
                        File::class,
                        'ID',
                        'Title'
                    )
                )
                ->displayIf("Type")->isEqualTo('File')->end(),
                Wrapper::create(
                    TextField::create(
                        'URL',
                        _t(__CLASS__ . '.URL', 'URL')
                    )
                )
                ->displayIf("Type")->isEqualTo('URL')->end(),
                Wrapper::create(
                    TextField::create(
                        'Email',
                        _t(__CLASS__ . '.EMAILADDRESS', 'Email Address')
                    )
                )
                ->displayIf("Type")->isEqualTo('Email')->end(),
                Wrapper::create(
                    TextField::create(
                        'Phone',
                        _t(__CLASS__ . '.PHONENUMBER', 'Phone Number')
                    )
                )
                ->displayIf("Type")->isEqualTo('Phone')->end(),
                CheckboxField::create(
                    'OpenInNewWindow',
                    _t(__CLASS__ . '.OPENINNEWWINDOW','Open link in a new window')
                )
                ->displayIf('Type')->isEqualTo("URL")
                ->orIf()->isEqualTo('File')
                ->orIf()->isEqualTo('SiteTree')->end()
            ]
        );

        $this->extend('updateCMSFields', $fields);

        return $fields;
    }


    /**
     * If the title is empty, set it to getLinkURL()
     * @return string
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();
        if (!$this->Title) {
            switch ($this->Type) {
                case 'URL':
                case 'Email':
                case 'Phone':
                    $this->Title = $this->{$this->Type};
                    break;
                case 'SiteTree':
                    $this->Title = $this->SiteTree()->MenuTitle;
                    break;
                default:
                    if ($this->Type && $component = $this->getComponent($this->Type)) {
                        $this->Title = $component->Title;
                    } else {
                        $this->Title = 'Link-' . $this->ID;
                    }
                    break;
            }

            $this->write();
        }
    }

    /**
     * Set CSS classes for templates
     * @param string $class CSS classes.
     * @return Link
     */
    public function setClass($class)
    {
        $classes = ($class) ? explode(' ', $class) : [];
        foreach ($classes as $key => $value) {
            $this->classes[$value] = $value;
        }
        return $this;
    }

    /**
     * Set style used for
     * @param string $style
     * @return Link
     */
    public function setStyle($style)
    {
        $this->style = $style;
        return $this;
    }

    /**
     * Sets allowed link types
     *
     * @param array $types Allowed type names
     * @return Link
     */
    public function setAllowedTypes($types = [])
    {
        $this->allowed_types = $types;
        return $this;
    }

    /**
     * Returns allowed link types
     * @return array
     */
    public function getTypes()
    {
        $types = $this->config()->get('types');

        if ($this->allowed_types) {
            // Prioritise local field over global settings
            $allowed_types = $this->allowed_types;
        }
        if ($allowed_types = $this->config()->get('allowed_types')) {
           foreach ($allowed_types as $type) {
                if (!array_key_exists($type, $types)) {
                    user_error("{$type} is not a valid link type");
                }
            }

            foreach (array_diff_key($types, array_flip($allowed_types)) as $key => $value) {
                unset($types[$key]);
            }
        }
        $this->extend('updateTypes', $types);
        return $types;
    }

    /**
     * Returns allowed link types with translations
     * @return array
     */
    public function geti18nTypes()
    {
        $i18nTypes = [];
        // Get translatable labels
        foreach ($this->Types as $key => $label) {
            $i18nTypes[$key] = _t(__CLASS__ . '.TYPE'.strtoupper($key), $label);
        }
        $this->extend('updatei18nTypes', $i18nTypes);
        return $i18nTypes;
    }

    /**
     * Returns available styles
     * @return array
     */
    public function getStyles()
    {
        $styles = $this->config()->get('styles');
        $this->extend('updateStyles', $styles);
        return $styles;
    }

    /**
     * Returns available styles with translations
     * @return array
     */
    public function geti18nStyles()
    {
        $i18nStyles = [];
        foreach ($this->styles as $key => $label) {
            $i18nStyles[$key] = _t(__CLASS__ . '.STYLE'.strtoupper($key), $label);
        }
        $this->extend('updatei18nStyles', $i18nStyles);
        return $i18nStyles;
    }

    /**
     * Works out what the URL for this link should be based on it's Type
     * @return string
     */
    public function getLinkURL()
    {
        if (!$this->ID) {
            return;
        }
        $type = $this->Type;
        switch ($type) {
            case 'URL':
                $LinkURL = $this->URL;
                break;
            case 'Email':
                $LinkURL = $this->Email ? 'mailto:' . $this->Email : null;
                break;
            case 'Phone':
                $phone = $this->obj('Phone')->PhoneFriendly();
                $LinkURL = $phone ? 'tel:' . $phone : null;
                break;
            case 'File':
            case 'SiteTree':
                if ($type && $component = $this->getComponent($type)) {
                    if (!$component->exists()) {
                        $LinkURL = false;
                    }
                    if ($component->hasMethod('Link')) {
                        $LinkURL = $component->Link() . $this->Anchor;
                    } else {
                        $LinkURL = _t(__CLASS__ . '.LINKMETHODMISSING', "Please implement a Link() method on your dataobject \"$type\"");
                    }
                }
                break;
            default:
                $LinkURL = false;
                break;
        }

        $this->extend('updateLinkURL', $LinkURL);
        return $LinkURL;
    }

    /**
     * Returns the css classes
     * @return string
     */
    public function getClass()
    {
        if ($this->style) {
            $this->setClass($this->style);
        }

        $classes = $this->classes;
        $this->extend('updateClasses', $classes);
        if (Count($classes)) {
            return implode(' ', $classes);
        }
    }

    /**
     * Returns the html class attribute
     * @return HTMLFragment
     */
    public function getClassAttr()
    {
        return $this->Class ? " class='$this->Class'" : null;
    }

    /**
     * Returns the html target attribute
     * @return string
     */
    public function getTarget()
    {
        return $this->OpenInNewWindow ? "_blank" : null;
    }

    /**
     * Returns the html target attribute
     * @return HTMLFragment
     */
    public function getTargetAttr()
    {
        return $this->OpenInNewWindow ? " target='_blank'" : null;
    }

    /**
     * Returns the html id attribute
     * @return string
     */
    public function getIDValue()
    {
        $id = null;
        $this->extend('updateIDValue', $id);
        return $id;
    }

    /**
     * Renders an HTML ID attribute
     * @return HTMLFragment
     */
    public function getIDAttr()
    {
        return $this->IDValue ? " id='$this->IDValue'" : null;
    }

    /**
     * Returns the description label of this links type
     * @return string
     */
    public function getLinkType()
    {
        $types = $this->config()->get('types');
        return isset($types[$this->Type]) ? _t(__CLASS__ . '.TYPE'.strtoupper($this->Type), $types[$this->Type]) : null;
    }

    /**
     * Renders an HTML anchor attribute for this link
     * @return HTML
     */
    public function forTemplate()
    {
        $link = '';
        if ($this->LinkURL) {
            $link = $this->renderWith($this->RenderTemplates);
        }
        $this->extend('updateTemplate', $link);
        return $link;
    }

    /**
     * Renders an HTML anchor tag for this link
     * This is an alias to {@link forTemplate()}
     *
     * @return string
     */
    public function getLayout()
    {
        return $this->forTemplate();
    }

    /**
     * Returns a list of rendering templates
     * @return array
     */
    public function getRenderTemplates()
    {
        $ClassName = $this->ClassName;

        if (is_object($ClassName)) $ClassName = get_class($ClassName);

        if (!is_subclass_of($ClassName, DataObject::class)) {
            throw new InvalidArgumentException($ClassName . ' is not a subclass of DataObject');
        }

        $templates = [];
        while ($next = get_parent_class($ClassName)) {
            $baseClassName = $this->baseClassName($ClassName);
            if ($this->style) {
                $templates[] = $baseClassName . '_' . $this->style;
            }
            $templates[] = $baseClassName;
            if ($next == DataObject::class) {
                return $templates;
            }
            $ClassName = $next;
        }
    }

    /**
     * Returns the base class without namespacing
     * @param  string $class
     * @return string
     */
    public function baseClassName($class)
    {
        $class = explode('\\', $class);
        return array_pop($class);
    }

    /**
     * Validate
     * @return ValidationResult
     */
    public function validate()
    {
        $valid = true;
        $message = null;
        $type = $this->Type;

        // Check if empty strings
        switch ($type) {
            case 'URL':
            case 'Email':
            case 'Phone':
                if ($this->{$type} == '') {
                    $valid = false;
                    $message = _t(
                        __CLASS__ . '.VALIDATIONERROR_EMPTY'.strtoupper($type),
                        'You must enter a {LinkType}',
                        [
                            'LinkType' => $this->LinkType
                        ]
                    );
                }
                break;
            case 'File':
            case 'SiteTree':
                if ($type && empty($this->{$type.'ID'})) {
                    $valid = false;
                    $message = _t(
                        __CLASS__ . '.VALIDATIONERROR_OBJECT',
                        'Please select a {LinkType}',
                        [
                            'LinkType' => $this->LinkType
                        ]
                    );
                }
                break;
        }
        // if its already failed don't bother checking the rest
        if ($valid) {
            switch ($type) {
                case 'URL':
                    $allowedFirst = ['#', '/'];
                    if (!in_array(substr($this->URL, 0, 1), $allowedFirst) && !filter_var($this->URL, FILTER_VALIDATE_URL)) {
                        $valid = false;
                        $message = _t(
                            __CLASS__ . '.VALIDATIONERROR_VALIDURL',
                            'Please enter a valid URL.  Be sure to include http:// for an external URL. or begin your internal url/anchor with a "/" character'
                        );
                    }
                    break;
                case 'Email':
                    if (!filter_var($this->Email, FILTER_VALIDATE_EMAIL)) {
                        $valid = false;
                        $message = _t(
                            __CLASS__ . '.VALIDATIONERROR_VALIDEMAIL',
                            'Please enter a valid Email address'
                        );
                    }
                    break;
                case 'Phone':
                    if (!preg_match("/^\+?[0-9a-zA-Z\-\s]*[\,\#]?[0-9\-\s]*$/", $this->Phone)) {
                        $valid = false;
                        $message = _t(
                            __CLASS__ . '.VALIDATIONERROR_VALIDPHONE',
                            'Please enter a valid Phone number'
                        );
                    }
                    break;
            }
        }

        $result = ValidationResult::create();
        if (!$valid) {
            $result->addError($message);
        }

        $this->extend('updateValidate', $result);

        return $result;
    }
}