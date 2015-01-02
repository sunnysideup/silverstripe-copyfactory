<?php


class CopyFactorySiteTreeExtension extends DataExtension {

	private static $db = array(
		//meta title embelishments
		'AllowCopyingOfRecords' => 'Int'
	);


	function updateCMSFields(FieldList $fields) {
		$fields->addFieldToTab(
			'Root.Copy',
			new DropdownField(
				"AllowCopyingOfRecords",
				_t("CopyFactory.ALLOW_COPYING_OF_RECORDS", "Allow Copying of Records"),
				array(
					0 => _t("CopyFactory.NO_COPYING_AT_ALL", "Do not allow copying of records (default setting)"),
					1 => _t("CopyFactory.DRY_RUN_ONLY", "Dry run only (not actual changes will be made)"),
					2 => _t("CopyFactory.ALLOW_COPYING", "Allow copying of records (please use with care)")
				)
			));
		return $fields;
	}

}
