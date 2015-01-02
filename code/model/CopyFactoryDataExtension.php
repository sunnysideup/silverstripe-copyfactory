<?php


class CopyFactoryDataExtension extends DataExtension {

	public static function get_extra_config($className, $extension, $args) {
		//foreach($config as $name => $value) {
		//	Config::inst()->update($className, $name, $value);
		//}
		// Force all subclass DB caches to invalidate themselves since their db attribute is now expired
		//DataObject::reset();
		return array(
			'has_one' => array(
				"Copy".$className => $className,
				"Copy".$className."_Completed" => $className
			)
		);
	}

	public function updateCMSFields(FieldList $fields) {
		parent::updateCMSFields($fields);
		$className = $this->owner->ClassName;
		$uncompletedField = $this->owner->CopyFromFieldName();
		$uncompletedFieldWithID = $uncompletedField."ID";
		$completedField = $this->owner->CopiedFromFieldName();
		$completedFieldWithID = $completedField."ID";
		//remove by default
		$fields->removeByName($uncompletedFieldWithID);
		$fields->removeByName($completedFieldWithID);
		$changeMessage =
					"<p class=\"message good\">".
						_t("CopyFactory.CHANGE_SETTINGS", "You can change the settings for copying in").
						" <a href=\"/admin/settings/\">"._t("CopyFactory.SITE_CONFIG", "The Site Config (see Copy Tab)")."</a>,".
						_t("CopyFactory.TURN_OFF_WHEN_NOT_IN_USE", "It is recommended you turn off the copy facility when not in use, as it will slow down the CMS.")."
					</p>";
		if($this->owner->exists()) {
			if($this->owner->$completedFieldWithID) {
				if($obj = $this->owner->$completedField()) {
					$fields->addFieldToTab(
						"Root.Copy",
						$copyQuestionIDField = new ReadonlyField(
							$completedField."_EXPLANATION",
							_t("CopyFactory.COPIED_FROM", "This record has been copied from: "),
							$this->CopyFactoryTitleMaker($obj)
						)
					);
				}
			}
			elseif($situation = SiteConfig::current_site_config()->AllowCopyingOfRecords) {
				if($situation == 1) {
					$message = _t(
						'CopyFactory.DRY_RUN_ONLY',
						"Dry run only --- any changes below will be tested once your press 'SAVE' but no actual changes will be made.  You will find a log of intended changes below for review."
					);
				}
				if($situation == 2) {
					$message = _t(
						'CopyFactory.THIS_IS_FOR_REAL',
						"Any changes below will be actioned once you press 'SAVE' - please use with care."
					);
				}
				$fields->addFieldToTab(
					"Root.Copy",
					$copyField = new LiteralField(
						$uncompletedFieldWithID."_WARNING",
						"<p class=\"warning message\">".$message."</p>".
						$changeMessage
					)
				);
				$copyableObjects = $className::get()
					->exclude(array("ID" => intval($this->owner->ID) - 0))
					->filter(array("ClassName" => $this->owner->ClassName));
				if($this->owner->hasMethod("additionalFiltersForCopyableObjects")) {
					$copyAbleObjects = $this->owner->additionalFiltersForCopyableObjects($copyableObjects);
				}
				//there are objects to copy from
				if($copyableObjects->count() > 0) {
					$fields->addFieldToTab(
						"Root.Copy",
						$copyField = new DropdownField(
							$uncompletedFieldWithID,
							_t(
								'CopyFactory.COPY_EXPLANATION',
								"Copy from {name}. CAREFUL - this will replace everything in the current {name} with the one copied from ...",
								'Explanation on how copying works',
								array('name' => $this->owner->i18n_singular_name())
							),
							$copyableObjects->map("ID", CopyFactory::preferred_title_field($this->owner))
						)
					);
					$copyField->setEmptyString(_t("CopyFactory.SELECT_ONE", "--- Select One ---"));
				}
				else {
					$fields->addFieldToTab(
						"Root.Copy",
						$copyField = new LiteralField(
							$uncompletedFieldWithID."_EXPLANATION",
							"<h2>".
							_t(
								'CopyFactory.COPY_FACTORY_HELP_NO_RECORDS',
								"There are no records to copy from."
							).
							"</h2>"
						)
					);
				}
			}
			else {
				$fields->addFieldToTab(
					"Root.Copy",
					$copyField = new LiteralField(
						"CopyFactoryNotTurnedOn",
						"<h2>".
						_t(
							'CopyFactory.COPY_FACTORY_TURNED_OFF',
							"Copying of records is currently not turned on."
						).
						"</h2>".
						$changeMessage
					)
				);
			}
			$source = CopyFactoryLog::get()
				->filter(array("CopyCausingClassName" => $this->owner->ClassName, "CopyCausingClassNameID" => $this->owner->ID))
				->exclude(array("CopyIntoClassName" => $this->owner->ClassName, "CopyIntoClassNameID" => $this->owner->ID))
				->exclude(array("CopyIntoClassName" => $this->owner->ClassName, "CopyFromClassNameID" => $this->owner->ID));
			if($source->count()) {
				$name = "COPY_CAUSING_GRIDFIELD";
				$title = _t("CopyFactory.COPY_CAUSING_TITLE", "Copy actions originated from this record.");
				$fields->addFieldToTab("Root.Copy", $this->gridFieldMaker($name, $title, $source));
			}
			$source = CopyFactoryLog::get()
				->filter(array("CopyIntoClassName" => $this->owner->ClassName, "CopyIntoClassNameID" => $this->owner->ID))
				//->exclude(array("CopyCausingClassName" => $this->owner->ClassName, "CopyCausingClassNameID" => $this->owner->ID))
				->exclude(array("CopyIntoClassName" => $this->owner->ClassName, "CopyFromClassNameID" => $this->owner->ID));
			if($source->count()) {
				$name = "COPY_INTO_GRIDFIELD";
				$title = _t("CopyFactory.COPY_INTO_TITLE", "Copy actioned into this record.");
				$fields->addFieldToTab("Root.Copy", $this->gridFieldMaker($name, $title, $source));
			}
			$source = CopyFactoryLog::get()
				->filter(array("CopyIntoClassName" => $this->owner->ClassName, "CopyFromClassNameID" => $this->owner->ID))
				->exclude(array("CopyIntoClassName" => $this->owner->ClassName, "CopyIntoClassNameID" => $this->owner->ID))
				->exclude(array("CopyCausingClassName" => $this->owner->ClassName, "CopyCausingClassNameID" => $this->owner->ID));
			if($source->count()) {
				$name = "COPY_FROM_GRIDFIELD";
				$title = _t("CopyFactory.COPY_FROM_TITLE", "Copy actions from this record into another record.");
				$fields->addFieldToTab("Root.Copy", $this->gridFieldMaker($name, $title, $source));
			}
		}
	}

	/**
	 * @param String $name
	 * @param String $title
	 * @param DataList $source
	 *
	 * @return GridField
	 */
	private function gridFieldMaker($name, $title, $source) {
		return new GridField(
			$name,
			_t("CopyFactory.COPY_FACTORY_LOG", "Copy Log: ").$title,
			$source,
			GridFieldConfig_RecordViewer::create(30)
		);
	}

	/**
	 *
	 * @return String
	 */
	public function CopyFromFieldName($withID = false){
		$str = Config::inst()->get("CopyFactory", "copy_fields_prefix").
			$this->FindOriginalObjectClassName();
		if($withID ) {
			$str .= "ID";
		}
		return $str;
	}

	/**
	 *
	 * @return String
	 */
	public function CopiedFromFieldName($withID = false){
		$str = Config::inst()->get("CopyFactory", "copy_fields_prefix").
			$this->FindOriginalObjectClassName().
			Config::inst()->get("CopyFactory", "completed_field_appendix");
		if($withID ) {
			$str .= "ID";
		}
		return $str;
	}

	/**
	 *
	 * @var DataObject
	 */
	private static $my_original_object = null;

	/**
	 * finds the obj
	 * @return DataObject
	 */
	private function FindOriginalObjectClassName(){
		if(!self::$my_original_object) {
			$obj = $this->owner;
			while($obj->hasExtension("CopyFactoryDataExtension")) {
				$finalObject = $obj;
				$obj = Injector::inst()->get(get_parent_class($obj));
			}
			self::$my_original_object = $finalObject;
		}
		return self::$my_original_object->ClassName;
	}


	/**
	 * provides a meaningful title for an object
	 * @return String ...
	 */
	public function CopyFactoryTitleMaker(){
		$methodOrField = $this->CopyFactoryPreferredTitleField();
		if($this->owner->hasMethod($methodOrField)) {
			return $this->owner->$methodOrField();
		}
		elseif($this->owner->hasMethod("get".$methodOrField)) {
			$methodName = "get".$methodOrField;
			return $this->owner->$methodName();
		}
		else {
			return $this->owner->$methodOrField;
		}
	}

	/**
	 *
	 * @return String
	 */
	public function CopyFactoryPreferredTitleField(){
		$titleMap = Config::inst()->get("CopyFactory", "title_map_for_display_of_record_name");
		if(isset($titleMap[$this->owner->ClassName])) {
			return $titleMap[$this->owner->ClassName];
		}
		return "Title";
	}

	/**
	 *
	 * @return String
	 */
	protected function getCopyFactorySessionName(){
		return
			Config::inst()->get("CopyFactory", "dry_run_for_session_base_name")
			."_".
			implode("_", array($this->ClassName, $this->ID));
	}

	/**
	 * we run the actual copying onAfterWrite
	 */
	function onAfterWrite(){
		parent::onAfterWrite();
		if(SiteConfig::current_site_config()->AllowCopyingOfRecords) {
			$fieldName = $this->owner->CopyFromFieldName();
			$fieldNameWithID = $fieldName."ID";
			if($this->owner->$fieldNameWithID) {
				if($copyFrom = $this->owner->$fieldName()) {
					$factory = CopyFactory::create($this->owner);
					$factory->copyObject($copyFrom, $this->owner);
				}
				else {
					// a little cleanup: lets reset ...
					$this->owner->$fieldNameWithID = 0;
					$this->owner->write();
				}
			}
		}
	}


}

