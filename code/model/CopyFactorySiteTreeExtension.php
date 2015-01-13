<?php


class CopyFactorySiteTreeExtension extends DataExtension {

	/**
	 * location for backup file e.g. /var/backups/db.sql
	 * @var String
	 */
	private static $full_location_for_db_backup_file = "";

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

	/**
	 * Adds a button the Site Config page of the CMS to rebuild the Lucene search index.
	 * @param FieldList $actions
	 */
	public function updateCMSActions(FieldList $actions) {
		$fileLocation = $this->owner->Config()->get("full_location_for_db_backup_file");
		if(file_exists($fileLocation)) {
			$lastChanged = _t('CopyFactory.BACKUP_WAS_LAST_MADE', 'last backup ... ')
				. date ("F d Y H:i:s.", filemtime($fileLocation));
		}
		else {
			$lastChanged = _t('CopyFactory.NO_BACKUP_IS_AVAILABLE', 'No Backup is Available ... ');
		}
		if(Permission::check("ADMIN")) {
			if($this->owner->AllowCopyingOfRecords) {
				if($fileLocation = $this->owner->Config()->get("full_location_for_db_backup_file")) {
					$actions->push(
						new FormAction(
							'doMakeDatabaseBackup',
							_t('CopyFactory.MAKE_DATABASE_BACKUP', 'Make Database Backup')."; ".$lastChanged
						)
					);
					if(file_exists($fileLocation)) {
						$actions->push(
							new FormAction(
								'doRestoreDatabaseBackup',
								_t('CopyFactory.RESTORE_DB_BACKUP_NOW', 'Restore Database Backup')
							)
						);
					}
				}
			}
		}
	}
}

class CopyFactorySiteTreeExtension_LeftAndMainExtension extends LeftAndMainExtension {

	/**
	 *
	 * @var Int
	 */
	private static $max_db_copies = 3;

	/**
	 *
	 * @inherit
	 */
	private static $allowed_actions = array(
		'doMakeDatabaseBackup',
		'restoreDatabaseBackup'
	);

	public function doMakeDatabaseBackup() {
		$outcome = $this->makeDatabaseBackup();
		if(!$outcome) {
			$message = _t('CopyFactory.DB_COPY_MADE', 'Database Copy Made');
		}
		else {
			$message = _t('CopyFactory.DB_COPY_NOT_MADE', 'Database Copy Could * Not * Be Made').": $outcome";
		}
		$this->owner->response->addHeader('X-Status', $message);
		return $this->owner->getResponseNegotiator()->respond($this->owner->request);
	}

	public function doRestoreDatabaseBackup() {
		$outcome = $this->restoreDatabaseBackup();
		if($outcome) {
			$message = _t('CopyFactory.DB_RESTORED', 'Database Restored');
		}
		else {
			$message = _t('CopyFactory.DB_NOT_RESTORED', 'Database * NOT * Restored');
		}
		$this->owner->response->addHeader('X-Status', $message);
		return $this->owner->getResponseNegotiator()->respond($this->owner->request);
	}

	/**
	 * copies back up files up one ...
	 * @return Mixed
	 */
	private function makeDatabaseBackup(){
		global $databaseConfig;
		if(Permission::check("ADMIN")) {
			if($fileLocation = Config::inst()->get("SiteConfig", "full_location_for_db_backup_file")) {
				$copyFileLocation = $fileLocation;
				$max = $this->owner->Config()->get("max_db_copies");
				for($i = $max; $i > -1; $i--) {
					$lowerFileLocation = $fileLocation.".".($i).".bak";
					$j = $i + 1;
					$higherFileLocation = $fileLocation.".".$j.".bak";
					if($i == $max) {
						if(file_exists($lowerFileLocation)) {
							unlink($lowerFileLocation);
						}
					}
					else {
						if(file_exists($lowerFileLocation)) {
							if(file_exists($higherFileLocation)) {
								unlink($higherFileLocation);
							}
							rename($lowerFileLocation, $higherFileLocation);
						}
					}
				}
				if(!isset($lowerFileLocation)) {
					$lowerFileLocation = $fileLocation.".0.bak";
				}

				if(file_exists($fileLocation)) {
					if(file_exists($lowerFileLocation)) {
						unlink($lowerFileLocation);
					}
					rename($fileLocation, $lowerFileLocation);
				}
				$command = "mysqldump -u ".$databaseConfig["username"]." -p".$databaseConfig["password"]." ".$databaseConfig["database"]." >  ".$fileLocation;
				return exec($command);
			}
		}
	}

	/**
	 * copies back up files up one ...
	 * @return Boolean
	 */
	private function restoreDatabaseBackup(){
		global $databaseConfig;
		if(Permission::check("ADMIN")) {
			$this->makeDatabaseBackup();
			if($fileLocation = Config::inst()->get("SiteConfig", "full_location_for_db_backup_file")) {
				$fileLocation = $fileLocation.".0.bak";
				if(file_exists($fileLocation)) {
					$command = "mysql -u ".$databaseConfig["username"]." -p".$databaseConfig["password"]." ".$databaseConfig["database"]." <  ".$fileLocation;
					exec($command);
					return true;
				}
			}
		}
		return false;
	}

}
