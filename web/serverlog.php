<?php

/**
 * File: serverlog.php.
 * Author: Ulrich Block
 * Contact: <ulrich.block@easy-wi.com>
 *
 * This file is part of Easy-WI.
 *
 * Easy-WI is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Easy-WI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Easy-WI.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Diese Datei ist Teil von Easy-WI.
 *
 * Easy-WI ist Freie Software: Sie koennen es unter den Bedingungen
 * der GNU General Public License, wie von der Free Software Foundation,
 * Version 3 der Lizenz oder (nach Ihrer Wahl) jeder spaeteren
 * veroeffentlichten Version, weiterverbreiten und/oder modifizieren.
 *
 * Easy-WI wird in der Hoffnung, dass es nuetzlich sein wird, aber
 * OHNE JEDE GEWAEHELEISTUNG, bereitgestellt; sogar ohne die implizite
 * Gewaehrleistung der MARKTFAEHIGKEIT oder EIGNUNG FUER EINEN BESTIMMTEN ZWECK.
 * Siehe die GNU General Public License fuer weitere Details.
 *
 * Sie sollten eine Kopie der GNU General Public License zusammen mit diesem
 * Programm erhalten haben. Wenn nicht, siehe <http://www.gnu.org/licenses/>.
 */

define('EASYWIDIR', dirname(__FILE__));
include(EASYWIDIR . '/stuff/methods/functions.php');
include(EASYWIDIR . '/stuff/methods/class_validator.php');
include(EASYWIDIR . '/stuff/methods/class_ftp.php');
include(EASYWIDIR . '/stuff/methods/vorlage.php');
include(EASYWIDIR . '/stuff/settings.php');
include(EASYWIDIR . '/stuff/keyphrasefile.php');

if (!isset($user_id) and !isset($admin_id)) {
	header('Location: login.php');
	die('Please allow redirection');
}

if (!isset($resellerLockupID)) {
    $resellerLockupID = $reseller_id;
}

if ($ui->id('id', 10, 'get')) {
    
	if (isset($admin_id)) {
        
        $query = $sql->prepare("SELECT u.`id`,u.`cname` FROM `gsswitch` g LEFT JOIN `userdata` u ON g.`userid`=u.`id` WHERE g.`id`=? AND g.`resellerid`=? LIMIT 1");
        $query->execute(array($ui->id('id', 10, 'get'), $resellerLockupID));
		while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
			$username = $row['cname'];
            $user_id = $row['id'];
		}
        
	} else {
		$username = getusername($user_id);
	}
    
    $query = $sql->prepare("SELECT g.`id`,g.`newlayout`,g.`rootID`,g.`serverip`,g.`port`,g.`protected`,AES_DECRYPT(g.`ftppassword`,?) AS `dftppass`,AES_DECRYPT(g.`ppassword`,?) AS `decryptedftppass`,s.`servertemplate`,t.`binarydir`,t.`shorten` FROM `gsswitch` g LEFT JOIN `serverlist` s ON g.`serverid`=s.`id` LEFT JOIN `servertypes` t ON s.`servertype`=t.`id` WHERE g.`id`=? AND g.`userid`=? AND g.`resellerid`=? LIMIT 1");
    $query->execute(array($aeskey, $aeskey, $ui->id('id', 10, 'get'), $user_id, $resellerLockupID));
	while ($row = $query->fetch(PDO::FETCH_ASSOC)) {

		$protected = $row['protected'];
		$servertemplate = $row['servertemplate'];
		$rootID = $row['rootID'];
		$serverip = $row['serverip'];
		$port = $row['port'];
        $shorten = $row['shorten'];
        $binarydir = $row['binarydir'];
        $ftppass = $row['dftppass'];

        if ($row['newlayout'] == 'Y') {
            $username .= '-' . $row['id'];
        }

		if ($protected == 'N' and $servertemplate > 1) {
			$shorten .= '-' . $servertemplate;
            $pserver = 'server/';
		} else if ($protected == 'Y') {
			$username .= '-p';
			$ftppass = $row['decryptedftppass'];
			$pserver = '';
		} else {
			$pserver = 'server/';
		}
	}

    if (isset($rootID)) {

        $query = $sql->prepare("SELECT `ip`,`ftpport` FROM `rserverdata` WHERE `id`=? AND `resellerid`=? LIMIT 1");
        $query->execute(array($rootID, $resellerLockupID));
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {

            $ftpport = $row['ftpport'];
            $ip = $row['ip'];

            $ftpConnect = new EasyWiFTP($ip, $ftpport, $username, $ftppass);

            $downloadChrooted = $ftpConnect->removeSlashes($pserver . $serverip . '_' . $port . '/' . $shorten . '/' . $binarydir . '/screenlog.0');

            if ($ftpConnect->ftpConnection) {

                if (!$ftpConnect->downloadToTemp($downloadChrooted, 32768)) {
                    $error = 'Cannot download screenlog from ' . $downloadChrooted;
                }

            } else {
                $error = 'Cannot connect to FTP Server ' . $ip . ':' . $ftpport;
            }
        }

        if (isset($ip)) {

            $log = (isset($error)) ? $error : nl2br($ftpConnect->getTempFileContent());

            $ftpConnect->tempHandle = null;
            $ftpConnect = null;

        } else {
            $log = 'Error: wrong rootID';
        }

    } else {
        $log = 'Error: No rootID';
    }
}

include(IncludeTemplate($template_to_use, 'gameserverlog.tpl'));
$sql = null;