<?php

/**
 * keeps track of any changes being made, fake or real
 *
 *
 */

class CopyFactoryLog extends DataObject {

	/**
	 * we can write faster to MyISAM?
	 *
	 */
	private static $create_table_options = array(
		'MySQLDatabase' => 'ENGINE=MyISAM'
	);

	private static $db = array(
		"StartTime" => "SS_Datetime",
		"Type" => "Enum('Unknown,Fake,Real', 'Unknown')",
		"CopyCausingClassName" => "Varchar(200)",
		"CopyCausingClassNameID" => "Int",
		"CopyFromClassNameID" => "Int",
		"CopyIntoClassName" => "Varchar(200)",
		"CopyIntoClassNameID" => "Int",
		"Action" => "Text"
	);

	private static $indexes = array(
		"CopyCausingClassName" => true,
		"CopyCausingClassNameID" => true,
		"CopyFromClassNameID" => true,
		"CopyIntoClassName" => true,
		"CopyIntoClassNameID" => true
	);

	private static $summary_fields = array(
		"StartTime",
		"Type",
		"CopyCause",
		"CopyFrom",
		"CopyInto",
		"FormattedShortAction"
	);

	private static $field_labels = array(
		"StartTime" => "Started",
		"Type" => "Real or Fake",
		"CopyCause" => "Started by",
		"CopyFrom" => "From",
		"CopyInto" => "Into",
		"Action" => "Description"
	);

	private static $searchable_fields = array(
		"Type" => "PartialMatchFilter",
		"Action" => "PartialMatchFilter"
	);

	private static $casting = array(
		"CopyCause" => "Varchar",
		"CopyFrom" => "Varchar",
		"CopyInto" => "Varchar",
		"FormattedShortAction" => "HTMLText"
	);

	private static $default_sort = "Created ASC";

	public function canEdit($member = null){
		return false;
	}

	public function canDelete($member = null){
		return false;
	}

	function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->addFieldToTab(
			"Root.Main",
			new ReadonlyField(
				"CopyCause",
				_t("CopyFactory.COPY_CAUSE", "Copy initiated by"),
				$this->getCopyCause()
			),
			"CopyCausingClassName"
		);
		$fields->addFieldToTab(
			"Root.Main",
			new ReadonlyField(
				"CopyFrom",
				_t("CopyFactory.COPY_FROM", "Copying from"),
				$this->getCopyFrom()
			),
			"CopyFromClassNameID"
		);
		$fields->addFieldToTab(
			"Root.Main",
			new ReadonlyField(
				"CopyInto",
				_t("CopyFactory.COPY_INTO", "Copying into"),
				$this->getCopyInto()
			),
			"CopyIntoClassName"
		);
		$fields->addFieldToTab(
			"Root.Main",
			new LiteralField(
				"Action",
				"<pre>$this->Action</pre>"
			)
		);
		return $fields;
	}

	/**
	 * Copy Causer Description
	 * @casted
	 * @return Str
	 */
	public function getCopyCause(){
		$className = $this->CopyCausingClassName;
		if($className) {
			$obj = $className::get()->byID($this->CopyCausingClassNameID);
			if($obj) {
				return CopyFactory::title_for_object($obj)." (".$className.")";
			}
		}
		return _t("CopyFactory.N_A", "n/a");
	}

	/**
	 * Copy From Description
	 * @casted
	 * @return Str
	 */
	public function getCopyFrom(){
		$className = $this->CopyIntoClassName;
		if($className) {
			$obj = $className::get()->byID($this->CopyFromClassNameID);
			if($obj) {
				return CopyFactory::title_for_object($obj)." (".$className.")";
			}
		}
		return _t("CopyFactory.N_A", "n/a");
	}

	/**
	 * Copy Into Description
	 * @casted
	 * @return Str
	 */
	public function getCopyInto(){
		$className = $this->CopyIntoClassName;
		if($className) {
			$obj = $className::get()->byID($this->CopyIntoClassNameID);
			if($obj) {
				return CopyFactory::title_for_object($obj)." (".$className.")";
			}
		}
		return _t("CopyFactory.N_A", "n/a");
	}


	/**
	 * Copy Into Description
	 * @casted
	 * @return Str
	 */
	public function getFormattedShortAction(){
		return DBField::create_field("HTMLText", str_replace("\n", "<br />", trim(substr($this->Action, 0, 799))));
	}


}

