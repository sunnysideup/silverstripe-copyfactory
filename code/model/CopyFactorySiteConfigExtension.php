<?php


class CopyFactorySiteConfigExtension extends DataExtension {

	private static $db = array(
		'AllowCopyingOfRecords' => 'Int'
	);


	function updateCMSFields(FieldList $fields) {
		$fields->addFieldToTab(
			'Root.Copy',
			$myDD = new DropdownField(
				"AllowCopyingOfRecords",
				_t("CopyFactory.ALLOW_COPYING_OF_RECORDS", "Allow Copying of Records"),
				array(
					0 => _t("CopyFactory.NO_COPYING_AT_ALL", "Do not allow copying of records (default setting)"),
					1 => _t("CopyFactory.DRY_RUN_ONLY", "Dry run only (not actual changes will be made)"),
					2 => _t("CopyFactory.ALLOW_COPYING", "Allow copying of records (please use with care)")
				)
			));
			$myDD->setRightTitle(_t("CopyFactory.TURN_IT_OFF", "It is recommended to turn on the ability to copy records only when required and to turn it off between copy sessions."));
		return $fields;
	}

}

