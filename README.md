Copy Factory
================================================================================


[![Build Status](https://travis-ci.org/sunnysideup/silverstripe-copyfactory.svg?branch=master)](https://travis-ci.org/sunnysideup/silverstripe-copyfactory)


This module helps you copy the contents of DataObjects, including their relations.


Developer
-----------------------------------------------
Nicolaas Francken [at] sunnysideup.co.nz


Requirements
-----------------------------------------------
see composer.json


Installation Instructions
-----------------------------------------------
1. Find out how to add modules to SS and add module as per usual.

2. Review configs and add entries to mysite/_config/config.yml
(or similar) as necessary.
In the _config/ folder of this module
you can usually find some examples of config options (if any).

3. add the CopyFactoryDataExtension as an extension to any Copy-Able DataObject.

4. After that you can add the following variables and methods (optional)

  (a). `additionalFiltersForCopyableObjects` (public method) - returns an array of fields to ignore in copy.

  (b). `getIgnoreInCopyFields` (public method) - returns an array of fields to ignore in copy.

  (c). `doCopyFactory (public method)

  (d). `ignore_in_copy_fields` (private static) - in case you are not using the getIgnoreInCopyFields method


```php
    /**
     * adds extra SQL to the filter list of copyable records
     *
     * @param DataList $dataList
     *
     * @return DataList
     */
    public function additionalFiltersForCopyableObjects($dataList){
      $dataList = $dataList->filter("MyField", "foo");
      return $dataList
    }

    /**
     * returns a list of fields (db + has_one including ID) to ignore
     * when copying into this record ...
     *
     * @return Array
     */
    public function getIgnoreInCopyFields(){
      return array("Title", "Code", "ImageID");
    }

    /**
     * runs additional copy actions.
     *
     * @param CopyFactory $factory
     * @param DataObject $copyFrom
     */
    public function doCopyFactory($factory, $copyFrom){
        $factory->copyHasManyRelation(
          $copyFrom,
          $this,
          $relationalMethodChildren = "Children",
          $relationalMethodParent = "ParentID,
          $copyChildrenAsWell = true,
        );
    }
```

For the most basic situation (without any special relationships between DataObjects),
you can leave out the `doCopyFactory` method.  For more complex situations, the following
methods can be added to a DataObject:
 - `copyHasOneRelation`, `attachToMoreRelevantHasOne`
 - `copyOriginalHasManyItems, `copyHasManyRelation`, `attachToMoreRelevantHasMany`
 - `copyOriginalManyManyItems`, `attachToMoreRelevantManyMany`


These methods are chainable, here is an example:

```php
    /**
     * runs additional copy actions.
     *
     * @param CopyFactory $factory
     * @param DataObject $copyFrom
     */
    public function doCopyFactory($factory, $copyFrom){
      $factory
        ->copyHasManyRelation(
          $copyFrom,
          $this,
          $relationalMethodChildren = "Children",
          $relationalMethodParent = "ParentID,
          $copyChildrenAsWell = true,
        )
        ->attachToMoreRelevantHasOne(
          $copyFrom,
          $this,
          $hasOneMethod = "MyImage",
          $dataListToChooseFrom = Image::get()->filter(array("Foo" => "Bar"));
        )
        ->copyOriginalManyManyItems(
          $copyFrom,
          $this,
          $manyManyMethod = "MyManyManyDataObjects",
        );
      //other stuff ...
      if($factory->getIsForReal()) {
        foreach($copyFrom->MyManyManyWithExtraFields() as $do) {
          $this->add($do, array("ExtraField" => $do->ExtraField));
        }
      }
    }
```

Complexities to consider
============================

These are just some situation to consider.  They
are not necessarily catered for in this module.

Option A:
----------------------------

object A has_one object B
COPY object A into C
- copy object B into D
- link B to D


Option B:
----------------------------

object A has_many objects B, C, D
COPY object A into C
- copy object B, C, D into E, F, G
- link B to E, etc...


Option C:
----------------------------

object A many_many objects B, C, D
COPY object A into C
- copy object B, C, D into E, F, G
- link B to E, F, G.

The question arises what happens if the newly create objects
have already been created as part of the copying process.


Strategy for solving these situations
----------------------------

1. work out if it is a copy situation or a situation where you link to the old item (in that case DO NOTHING);
2. look in copy register for matching copied items, if it is not in the copy register then create a new one;
3. examine the rules that apply to what is the most relevant item;
4. switch the reference(s) to new item;
5. mark, in the copy register the use of this particular reference, so it can only be used once
