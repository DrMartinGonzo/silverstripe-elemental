<?php

/**
 * @package elemental
 */
class ElementalArea extends WidgetArea
{
    public function Elements()
    {
        $result = $this->getComponents('Widgets');
        if ($result instanceof UnsavedRelationList) {
            return array();
        }

        $list = new HasManyList('BaseElement', $result->getForeignKey());
        $list->setDataModel($this->model);
        $list->sort('Sort ASC');

        $list = $list->forForeignID($this->ID);

        return $list;
    }

    /**
     * @return HasManyList
     */
    public function ItemsToRender() {
        $list = $this->Elements()->filter(array(
            'Enabled' => 1
        ));

        return $list;
    }

    /**
    * Return an ArrayList of pages with the Element Page Extension
    *
    * @return ArrayList
    */
    public function getOwnerPage()
    {
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, 'SiteTree')) {
                $object = singleton($class);
                $classes = ClassInfo::subclassesFor('ElementPageExtension');
                $isElemental = false;

                foreach ($classes as $extension) {
                    if ($object->hasExtension($extension)) {
                        $isElemental = true;
                    }
                }

                if ($isElemental) {
                    $page = $class::get()->filter('ElementAreaID', $this->ID);
                    if ($page && $page->exists()) {
                        return $page->first();
                    }
                }
            }
        }

        return false;
    }
}
