<?php


class CopyFactory extends Object {

	/**
	 * saves all the changes to the debug::log file..
	 * needs to be set manually
	 * @var Boolean
	 */
	private static $debug = false;

	/**
	 * is this a real copy event?
	 * @var Boolean
	 */
	private static $for_real = false;

	/**
	 * the time the copy was started.
	 * @var Int
	 */
	private static $start_time = 0;

	/**
	 * list of classes and their preferred title field
	 * e.g.
	 * MyDataObject => "FancyTitle"
	 *
	 * @var Array
	 */
	private static $title_map_for_display_of_record_name = array();

	/**
	 * The part at the start of the copy field
	 * e.g. MyDataObject becomes CopyMyDataObject.
	 *
	 * @var String
	 */
	private static $copy_fields_prefix = "Copy";
	/**
	 * the field where we save the ID of the completed copy
	 * action should be the same of the field where we saved the original
	 * dataobject to be copied + an appendix.
	 * e.g. CopyMyDataObject becomes CopyMyDataObject_Completed.
	 *
	 * @var String
	 */
	private static $completed_field_appendix = "_Completed";

	/**
	 * holds all the singletons
	 * @var array
	 */
	private static $singleton_holder = array();

	/**
	 * base name for session variables...
	 * @var String
	 */
	private static $dry_run_for_session_base_name = "CopyFactorySessionVariable";

	/**
	 * static variable that holds the details for the current dry run...
	 * @var string
	 */
	private static $dry_run_for_class_name = "";

	/**
	 * static variable that holds the details for the current dry run...
	 * @var string
	 */
	private static $dry_run_for_id = "";

	/**
	 * returns a CopyFactory specific to a Class.
	 * You must provide an object class name.
	 *
	 * @param DataObject $obj
	 *
	 * @return CopyFactory
	 */
	public static function create() {
		//allow up to ten minutes for copy ...
		increase_time_limit_to(600);
		$args = func_get_args();
		if(!$args || count($args) != 1) {
			user_error("Please provide an Object for your CopyFactory - like this: CopyFactory::create($myDataObject);");
		}
		$obj = array_shift($args);
		if(!($obj instanceof DataObject)) {
			user_error("First argument provided should be a DataObject.");
		}
		//set basic details if no copy has run thus far ...
		if(count(self::$singleton_holder) == 0) {
			$startTime = time();
			Config::inst()->update("CopyFactory", "dry_run_for_class_name", $obj->ClassName);
			Config::inst()->update("CopyFactory", "dry_run_for_id", $obj->ID);
			Config::inst()->update("CopyFactory", "start_time", $startTime);
			// dry run only...
			if(SiteConfig::current_site_config()->AllowCopyingOfRecords == 1) {
				Config::inst()->update("CopyFactory", "for_real", false);
				self::add_to_session("
					===========================================================
					Starting dry run: ".date("r", $startTime)."
					===========================================================
					===========================================================
				");
			}
			// for real ...
			if(SiteConfig::current_site_config()->AllowCopyingOfRecords == 2) {
				Config::inst()->update("CopyFactory", "for_real", true);
				// we cant do too many writes ... in a live environment
				self::add_to_session("
					===========================================================
					Starting real copy task: ".date("r", $startTime)."
					===========================================================
					===========================================================
				");
			}
		}
		if(!isset(self::$singleton_holder[$obj->ClassName])) {
			self::$singleton_holder[$obj->ClassName] = new CopyFactory();
		}

		self::$singleton_holder[$obj->ClassName]->myClassName = $obj->ClassName;
		self::$singleton_holder[$obj->ClassName]->isForReal = Config::inst()->get("CopyFactory", "for_real") ? true : false;
		if(Director::isDev()) {
			self::$singleton_holder[$obj->ClassName]->recordSession = true;
		}
		else {
			self::$singleton_holder[$obj->ClassName]->recordSession = Config::inst()->get("CopyFactory", "for_real") ? false : true;
		}
		return self::$singleton_holder[$obj->ClassName];
	}


	/**
	 * adds additional info to current session
	 * @param String $action
	 * @param DataObject $copyFrom
	 * @param DataObject $copyInto
	 */
	protected static function add_to_session($action, $copyFrom = null, $copyInto = null) {
		$obj = new CopyFactoryLog();
		$obj->Type = Config::inst()->get("CopyFactory", "for_real") ? "Real" : "Fake";
		$obj->StartTime = Config::inst()->get("CopyFactory", "start_time");
		$obj->CopyCausingClassName = Config::inst()->get("CopyFactory", "dry_run_for_class_name");
		$obj->CopyCausingClassNameID = Config::inst()->get("CopyFactory", "dry_run_for_id");
		if($copyFrom) {
			$obj->CopyFromClassNameID = $copyFrom->ID;
		}
		if($copyInto) {
			$obj->CopyIntoClassName = $copyInto->ClassName;
			$obj->CopyIntoClassNameID = $copyInto->ID;
		}
		$obj->Action = $action;
		$obj->write();
		if(Config::inst()->get("CopyFactory", "debug")) {
			$copyFromLine = "";
			if($copyFrom && $copyFrom->exists()) {
				$copyFromLine = "FROM: ".self::title_for_object($copyFrom)." - ".$copyFrom->ClassName.".".$copyFrom->ID."\n";
			}
			$copyIntoLine = "";
			if($copyInto && $copyInto->exists()) {
				$copyIntoLine = "INTO: ".self::title_for_object($copyInto)." - ".$copyInto->ClassName.".".$copyInto->ID."\n";
			}
			debug::log(
				$copyFromLine.
				$copyIntoLine.
				$action
			);
		}
	}


	/**
	 * provides a meaningful title for an object
	 * @return String ...
	 */
	public static function title_for_object($obj){
		$methodOrField = self::preferred_title_field($obj);
		if($obj->hasMethod($methodOrField)) {
			return $obj->$methodOrField();
		}
		elseif($obj->hasMethod("get".$methodOrField)) {
			$methodName = "get".$methodOrField;
			return $obj->$methodName();
		}
		else {
			return $obj->$methodOrField;
		}
	}

	/**
	 * returns the name of a method / db field
	 * that can be used to describe the object ...
	 *
	 * @param DataObject $object
	 *
	 * @return String
	 */
	public static function preferred_title_field($obj){
		$titleMap = Config::inst()->get("CopyFactory", "title_map_for_display_of_record_name");
		if(isset($titleMap[$obj->ClassName])) {
			return $titleMap[$obj->ClassName];
		}
		return "Title";
	}

	/**
	 * are records actually been written ...
	 *
	 * @var Boolean
	 */
	protected $isForReal = "";

	/**
	 * used for reference only
	 * saves the class of the current singleton instance
	 * that initiated the copy
	 *
	 * @var String
	 */
	protected $recordSession = "";

	/**
	 * used for reference only
	 * saves the class of the current singleton instance
	 * that initiated the copy
	 *
	 * @var String
	 */
	protected $myClassName = "";

	/**
	 * fields that are never copied
	 * @var array
	 */
	protected $baseIgnoreFields = array(
		"Created",
		"LastEdited",
		"ID"
	);

	/**
	 * set a list of fields that should always be ignored ...
	 * we add the base array to it at the same time ...
	 * @param Array $array
	 */
	public function setIgnoreFields($array) {
		$this->ignoreFields = array_merge($this->baseIgnoreFields, $array);
	}

	/**
	 * add one field to ignore
	 * @param String $fieldName
	 */
	public function addIgnoreField($fieldName) {
		$this->ignoreFields[] = $fieldName;
	}

	/**
	 *
	 * @param Boolean $b
	 *
	 * @return CopyFactory
	 */
	public function setIsForReal($b){
		return $this->isForReal = $b;
		return $this;
	}

	/**
	 *
	 * @return Boolean
	 */
	public function getIsForReal(){
		return $this->isForReal;
	}

	/**
	 *
	 * @return String
	 */
	protected function getCopyFactorySessionName(){
		return
			$this->Config()->get("dry_run_for_session_base_name")
			."_".
			$this->Config()->get("dry_run_for_class_name")
			."_".
			$this->Config()->get("dry_run_for_id");
	}



	/*****************************************************
	 *  COPYING METHODS
	 *****************************************************/

	/**
	 * Copy one object into another including has-one fields
	 *
	 * @param DataObject $copyFrom
	 * @param DataObject $newObject
	 * @param Boolean $overwriteValues - overwrite values that exist
	 *
	 * @return CopyFactory
	 */
	public function copyObject($copyFrom, $newObject, $overwriteValues = true){

		//get ignore fields
		if($newObject->hasMethod("getIgnoreInCopyFields")) {
			$this->setIgnoreFields($newObject->getIgnoreInCopyFields());
		}
		elseif($array = Config::inst()->get($newObject->ClassName, "ignore_in_copy_fields")) {
			if(is_array($array) && count($array)) {
				$this->setIgnoreFields($array);
			}
		}

		//get copy field
		$this->addIgnoreField($newObject->CopyFromFieldName($withID = true));
		$this->addIgnoreField($newObject->CopiedFromFieldName($withID = true));

		//get copy-able fields
		$dbFields = Config::inst()->get($copyFrom->ClassName, "db");
		$hasOneFields = Config::inst()->get($copyFrom->ClassName, "has_one");

		//has-one fields fixup
		foreach($hasOneFields as $key => $field) {
			$hasOneFields[$key."ID"] = $field;
			unset($hasOneFields[$key]);
		}
		$fields = $dbFields + $hasOneFields;
		//remove ignore fields ...
		foreach($this->ignoreFields as $ignoreField) {
			unset($fields[$ignoreField]);
		}
		//flip
		$fields = array_keys($fields);
		if($this->recordSession) {
			self::add_to_session(
				"
					====================================
					*** COPYING INTO
					".($this->myClassName != $copyFrom->ClassName ? "ERROR: ClassName mismatch: ".$this->myClassName." != ".$copyFrom->ClassName : "")."
					Values will ".($overwriteValues ? "" : "NOT")." be overridden.
					DB Fields: ".implode(", ", array_keys($dbFields))."
					HAS ONE Fields: ".implode(", ", array_keys($hasOneFields))."
					IGNORE Fields: ".implode(", ", $this->ignoreFields)."
					FINAL Fields: ".implode(", ", $fields)."
					====================================
				",
				$copyFrom,
				$newObject
			);
		}
		if($this->recordSession) {$copySessionRecording = "";}
		foreach($fields as $field) {
			if(!$newObject->$field || $overwriteValues) {
				$value = $copyFrom->$field;
				if(is_object($value)) {
					$value = $value->raw();
				}
				if($this->recordSession) {$copySessionRecording .= "\r\nSetting '$field' to '$value'";}
				if($this->isForReal) {
					$newObject->$field = $value;
				}
			}
		}
		$copyField = $newObject->CopyFromFieldName($withID = true);
		$copyFieldCompleted = $newObject->CopiedFromFieldName($withID = true);
		//important - reset, so that it does not get into a loop.
		if($this->recordSession) {self::add_to_session("$copySessionRecording setting '$copyField' to zero setting '$copyFieldCompleted' to '".$copyFrom->ID."'",$copyFrom, $newObject);}
		if($this->isForReal) {
			if($newObject->exists()) {
				$newObject->$copyFieldCompleted = $copyFrom->ID;
				$newObject->$copyField = 0;
			}
			$newObject->write();
		}
		//now we do all the other methods ...
		if($newObject->hasMethod("doCopyFactory")) {
			if($this->recordSession) {self::add_to_session("Now calling doCopyFactory ... ", $copyFrom, $newObject);}
			$newObject->doCopyFactory($this, $copyFrom);
		}
		return $this;
	}

	/**
	 * From a copied object, copy the has-one of the copyFrom Object to the newObject.
	 * Old and new copy point to the same has-one record.
	 *
	 * @param DataObject $copyFromParent
	 * @param DataObject $newObjectParent
	 * @param String $relationalFieldForChildWithoutID - e.g. MyImage
	 *
	 * @return CopyFactory
	 */
	public function copyOriginalHasOneItem($copyFromParent, $newObjectParent, $relationalFieldForChildWithoutID) {
		if($this->recordSession) {
			self::add_to_session("
					====================================
					COPY ORIGINAL HAS-ONE RELATION: $relationalFieldForChildWithoutID
					Children will link to original record (will not be copied)
					====================================
				",
				$copyFromParent,
				$newObjectParent
			);
		}
		$relationalFieldForChildWithID = $relationalFieldForChildWithoutID . "ID";
		if($this->isForReal) {
			$newObjectParent->$relationalFieldForChildWithID = $copyFromParent->$relationalFieldForChildWithID;
			$newObjectParent->write();
		}
		return $this;
	}

	/**
	 * Usage: an object has one child
	 * and we want to also copy the child and add it to the
	 * copied into parent ...
	 *
	 * @param DataObject $copyFromParent
	 * @param DataObject $newObjectParent
	 * @param String $relationalFieldForChildWithoutID -
	 *    this is the field on the parent that provides
	 *    its child (the single-child / has one child )WITHOUT the ID part.
	 *
	 * @return CopyFactory
	 */
	function copyHasOneRelation($copyFromParent, $newObjectParent, $relationalFieldForChildWithoutID) {
		if($this->recordSession) {self::add_to_session("
			====================================
			COPY HAS-ONE RELATION: $relationalFieldForChildWithoutID
			====================================
			", $copyFromParent, $newObjectParent);
		}
		if($copyFromChildObject = $copyFromParent->$relationalFieldForChildWithoutID()) {
			$className = $copyFromChildObject->ClassName;
			$childCopyField = $copyFromChildObject->CopyFromFieldName($withID = true);
			// what are we going to do?
			if($this->recordSession) {
				self::add_to_session("Creating a new object '$className' linking to '$relationalFieldForChildWithoutID' in parent.", $copyFromParent, $newObjectParent);
			}

			//create object and set parent ...
			$newObjectChildObject = $className::create();
			$newObjectChildObject->ClassName = $className;
			//does the child also copy ...
			//we copy the data here so that we dont run into validation errors
			$obj = CopyFactory::create($newObjectChildObject);
			$obj->copyObject($copyFromChildObject, $newObjectChildObject);

			if($this->isForReal) {
				//we reset the copy field here so that the copy can run another
				//time and do the has-many and many-many parts
				$newObjectChildObject->$childCopyField = intval($copyFromChildObject->ID);
				$relationalFieldForChildWithID = $relationalFieldForChildWithoutID."ID";
				$newObjectChildObject->$relationalFieldForChildWithID = $newObjectChildObject->ID;
				$newObjectChildObject->write();
				$newObjectChildObject = $className::get()->byID($newObjectChildObject->ID);
			}

			// setting child again - just in case ...
			if($this->isForReal) {
				$newObjectChildObject->$relationalFieldForChildWithID = $newObjectChildObject->ID;
				$newObjectChildObject->write();
			}
			if($this->recordSession) {
				if(!$newObjectChildObject){
					self::add_to_session("ERROR:  did not create object listed above", $copyFromChildObject, $newObjectChildObject);
				}
				else {
					self::add_to_session("CREATED object", $copyFromChildObject, $newObjectChildObject);
				}
				if($newObjectParent->$relationalFieldForChildWithID != $newObjectChildObject->ID) {
					self::add_to_session(
						"ERROR: broken link ... '".$newObjectParent->$relationalFieldForChildWithID."' is not equal to '".$newObjectChildObject->ID."'",
						$copyFromChildObject,
						$newObjectChildObject
					);
					//hack fix
				}
				else {
					self::add_to_session("Saved with correct new parent field ($relationFieldForParentWithID) ID: ".$newObjectChildObject->$relationFieldForParentWithID, $copyFromChildObject, $newObjectChildObject);
				}
			}
		}
		return $this;
	}

	/**
	 * Usage: Find the copied ("NEW") equivalent of the old has-one relation and attach it to the newObject ...
	 * @param DataObject $copyFrom
	 * @param DataObject $newObject
	 * @param String $hasOneMethod - the fieldname (method) of the has one relations (e.g. MyOtherDataObject)
	 * @param DataList $dataListToChooseFrom
	 *
	 * @return CopyFactory
	 */
	public function attachToMoreRelevantHasOne($copyFrom, $newObject, $hasOneMethod, $dataListToChooseFrom) {
		$fieldNameWithID = $hasOneMethod."ID";
		if($this->recordSession) {
			self::add_to_session("
					====================================
					ATTACH TO MORE RELEVANT HAS-ONE
					FIELD $hasOneMethod
					CONSTRAINT: ".$dataListToChooseFrom->sql()."
					====================================
				",
				$copyFrom,
				$newObject
			);
		}
		if($copyFrom->$fieldNameWithID) {
			$dataListToChooseFrom = $dataListToChooseFrom
				->filter(array($copyFrom->CopiedFromFieldName($withID = true) => $copyFrom->$fieldNameWithID))
				->Sort("Created DESC");
			$count = $dataListToChooseFrom->count();
			if($count == 1 && $newAttachment = $dataListToChooseFrom->First()) {
				if($this->recordSession) {self::add_to_session("Found Matching record.", $copyFrom, $newObject);}
				if($this->isForReal) {
					$newObject->$fieldNameWithID = $newAttachment->ID;
					$newObject->write();
				}
			}
			else {
				if($this->recordSession) {
					if($count > 1) {
						self::add_to_session("ERROR: found too many Matching records.", $copyFrom, $newObject);
					}
					elseif($count = 0) {
						self::add_to_session("ERROR: Could not find any Matching records.", $copyFrom, $newObject);
					}
					else {
						self::add_to_session("ERROR: There was an error retrieving the matching record.", $copyFrom, $newObject);
					}
				}
				if($this->isForReal) {
					$newObject->$fieldNameWithID = 0;
					$newObject->write();
				}
			}
		}
		else {
			self::add_to_session("copyFrom object does not have a value for: $fieldNameWithID", $copyFrom, $newObject);
		}
		if($this->recordSession) {self::add_to_session("*** END OF attachToMoreRelevantHasOne ***", $copyFrom, $newObject);}
		return $this;
	}

	/**
	 * Usage: an object has many children but we do NOT copy the children ...
	 *
	 * @param DataObject $copyFromParent
	 * @param DataObject $newObjectParent
	 * @param String $relationalFieldForChildren - this is the field on the parent that provides the children (e.g. Children or Images) WITHOUT the ID part.
	 * @param String $relationFieldForParent - this is the field on the children that links them back to the parent.
	 *
	 *  @return CopyFactory
	 */
	function copyOriginalHasManyItems($copyFromParent, $newObjectParent, $relationalFieldForChildren, $relationFieldForParentWithoutID) {
		user_error("This is not possible, because the many-side can't link to two objects (the copyFrom and copyInto part.");
	}

	/**
	 * Usage: an object has many children
	 * and we want to also copy the children and add them to the
	 * copied into parent ...
	 *
	 * @param DataObject $copyFromParent
	 * @param DataObject $newObjectParent
	 * @param String $relationalFieldForChildren - this is the field on the parent that provides the children (e.g. Children or Images) WITHOUT the ID part.
	 * @param String $relationFieldForParentWithoutID - this is the field on the children that links them back to the parent.
	 *
	 * @return CopyFactory
	 */
	function copyHasManyRelation($copyFromParent, $newObjectParent, $relationalFieldForChildren, $relationFieldForParentWithoutID) {
		if($this->recordSession) {
			self::add_to_session("
				====================================
				COPY HAS-MANY RELATION:
				CHILDREN METHOD: '$relationalFieldForChildren' and
				PARENT METHOD: '$relationFieldForParentWithoutID'
				====================================
				",$copyFromParent,$newObjectParent
			);
		}
		foreach($copyFromParent->$relationalFieldForChildren() as $copyFromChildObject) {
			$className = $copyFromChildObject->ClassName;
			$relationFieldForParentWithID = $relationFieldForParentWithoutID."ID";
			$childCopyField = $copyFromChildObject->CopyFromFieldName($withID = true);
			if($this->recordSession) {self::add_to_session("Creating a new object '$className'; adding parent field ($relationFieldForParentWithID) ID: ".$newObjectParent->ID, $copyFromParent, $newObjectParent);}
			//create object and set parent ...
			$newObjectChildObject = new $className();
			if($this->isForReal) {
				$newObjectChildObject->$relationFieldForParentWithID = $newObjectParent->ID;

				//does the child also copy ...
				//we copy the data here so that we dont run into validation errors
				$obj = CopyFactory::create($newObjectChildObject);
				$obj->copyObject($copyFromChildObject, $newObjectChildObject);
				//we reset the copy field here so that the copy can run another
				//time and do the has-many and many-many parts
				//$newObjectChildObject->$childCopyField = intval($copyFromChildObject->ID);
				//$newObjectChildObject->write();
				//retrieve it again to set more details ...
				$newObjectChildObject = $className::get()->byID($newObjectChildObject->ID);

				// setting parent again - just in case ...
				$newObjectChildObject->$relationFieldForParentWithID = $newObjectParent->ID;
				$newObjectChildObject->write();
			}
			if($this->recordSession) {
				if(!$newObjectChildObject){
					self::add_to_session("ERROR: did not create object listed above", $copyFromChildObject, $newObjectChildObject);
				}
				else {
					self::add_to_session("CREATED object", $copyFromChildObject, $newObjectChildObject);
				}
				if($newObjectChildObject->$relationFieldForParentWithID != $newObjectParent->ID) {
					self::add_to_session("
						ERROR: broken link ...  '".$newObjectChildObject->$relationFieldForParentWithID."' is not equal to '".$newObjectParent->ID."'",
						$copyFromChildObject, $newObjectChildObject
					);
					//hack fix
				}
				else {
					self::add_to_session("Saved with correct new parent field ($relationFieldForParentWithID) ID: ".$newObjectChildObject->$relationFieldForParentWithID, $copyFromChildObject, $newObjectChildObject);
				}
			}
		}
		if($this->recordSession) {self::add_to_session("*** END OF copyHasManyRelation ***", $copyFromParent, $newObjectParent);}
		return $this;
	}

	/**
	 * Usage: an object has many children, the children have already been copied, but they are not pointing at the new parent object.
	 *
	 * @param DataObject $copyFromParent
	 * @param DataObject $newObjectParent
	 * @param String $relationalFieldForChildren - this is the field on the parent that provides the children (e.g. Children or Images) WITHOUT the ID part.
	 * @param String $relationFieldForParentWithoutID - this is the field on the children that links them back to the parent.
	 * @param DataList $dataListToChooseFrom - selection of children that are best matches ...
	 *
	 * @return CopyFactory
	 */
	public function attachToMoreRelevantHasMany($copyFromParent, $newObjectParent, $relationalFieldForChildren, $relationFieldForParentWithoutID, $dataListToChooseFrom){
		user_error("The attachToMoreRelevantHasMany method is be completed.");
		return $this;
	}

	/**
	 * copies Many-Many relationship Without copying the items linked to ...
	 * @param DataObject $copyFrom
	 * @param DataObject $newObject
	 * @param String $manyManyMethod
	 * @param Array $extraFields - e..g Field1, Field2
	 *
	 * @return CopyFactory
	 */
	public function copyOriginalManyManyItems($copyFrom, $newObject, $manyManyMethod, $extraFields = array()) {
		if($this->recordSession) {
			self::add_to_session("
				====================================
				COPY Original Many Many Items
				MANY-MANY METHOD: '$manyManyMethod'
				EXTRAFIELDS: '".implode(", ", $extraFields)."'
				====================================
				",
				$copyFrom, $newObject
			);
		}
		//remove current ones on NewObject
		if($this->forReal) {
			$newObject->$manyManyMethod()->removeAll();
		}
		if($this->isForReal) {
			if(count($extraFields) == 0) {
				$ids = $copyFrom->$manyManyMethod()->Column("ID");
				if($this->recordSession) {self::add_to_session("copying ".count($ids)." items into $manyManyMethod.", $copyFrom, $newObject);}
				$newObject->$manyManyMethod()->addMany($ids);
			}
			else {
				$count = 0;
				foreach($copyFrom->$manyManyMethod() as $itemToAdd) {
					$count++;
					unset($newExtraFieldsArray);
					$newExtraFieldsArray = array();
					foreach($extraFields as $extraField) {
						$newExtraFieldsArray[$extraField] = $itemToAdd->$extraField;
					}
					$newObject->$manyManyMethod()->add($itemToAdd, $newExtraFieldsArray);
				}
				if($this->recordSession) {self::add_to_session("copying ".$count." items into $manyManyMethod, with extra Fields.", $copyFrom, $newObject);}
			}
		}
		return $this;
	}

	/***
	 * finds copied items for a many-many relationship
	 * make sure the many-many relation is also copy-able
	 * @param DataObject $copyFrom
	 * @param DataObject $newObject
	 * @param String $manyManyMethod
	 * @param DataList $dataListToChooseFrom
	 * @param Array $extraFields - many_many_ExtraFields
	 *
	 * @return CopyFactory
	 */
	public function attachToMoreRelevantManyMany($copyFrom, $newObject, $manyManyMethod, $dataListToChooseFrom, $extraFields = array()) {
		if($this->recordSession) {
			self::add_to_session("
				====================================
				ATTACH TO MORE RELEVANT MANY-MANY
				MANY-MANY METHOD: $manyManyMethod
				CONSTRAINT: ".$dataListToChooseFrom->sql()."
				EXTRA-FIELDS: '".implode(", ", $extraFields)."'
				====================================
				",
				$copyFrom,
				$newObject
			);
		}
		//remove current ones on NewObject
		if($this->forReal) {
			$newObject->$manyManyMethod()->removeAll();
		}
		if($copyFrom->$manyManyMethod()->count()) {
			foreach($copyFrom->$manyManyMethod() as $manyManyRelation) {
				$myDataListToChooseFrom = $dataListToChooseFrom
					->filter(array($manyManyRelation->CopiedFromFieldName($withID = true) => $manyManyRelation->ID))
					->Sort("Created DESC");
				$count = $myDataListToChooseFrom->count();
				if($count == 1 && $newAttachment = $myDataListToChooseFrom->First()) {
					if($this->recordSession) {self::add_to_session("Found Matching record.", $copyFrom, $newObject);}
					if(count($extraFields)) {
						unset($newExtraFieldsArray);
						$newExtraFieldsArray = array();
						foreach($extraFields as $extraField) {
							$newExtraFieldsArray[$extraField] = $newAttachment->$extraField;
						}
						if($this->isForReal) {
							$newObject->$manyManyMethod()->add($newAttachment, $newExtraFieldsArray);
						}
					}
					else {
						if($this->isForReal) {
							$newObject->$manyManyMethod()->add($newAttachment);
						}
					}
				}
				else {
					if($this->recordSession) {
						if($count > 1) {
							self::add_to_session("ERROR: found too many Matching records.", $copyFrom, $newObject);
						}
						elseif($count = 0) {
							self::add_to_session("ERROR: Could not find any Matching records.", $copyFrom, $newObject);
						}
						else {
							self::add_to_session("ERROR: There was an error retrieving the matching record.", $copyFrom, $newObject);
						}
					}
					if($this->isForReal) {
						//nothing to do here as they have already been deleted.
					}
				}
			}
		}
		else {
			self::add_to_session("copyFrom object does not have a value for", $copyFrom, $newObject);
		}
		if($this->recordSession) {self::add_to_session("*** END OF attachToMoreRelevantManyMany ***", $copyFrom, $newObject);}
		return $this;
	}

}

