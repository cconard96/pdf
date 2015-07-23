<?php
/**
 * @version $Id$
 -------------------------------------------------------------------------
 LICENSE

 This file is part of PDF plugin for GLPI.

 PDF is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.

 PDF is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with Reports. If not, see <http://www.gnu.org/licenses/>.

 @package   pdf
 @authors   Nelly Mahu-Lasson, Remi Collet
 @copyright Copyright (c) 2009-2015 PDF plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://forge.indepnet.net/projects/pdf
 @link      http://www.glpi-project.org/
 @since     2009
 --------------------------------------------------------------------------
 */

function plugin_pdf_postinit() {
   global $CFG_GLPI, $PLUGIN_HOOKS;

   foreach ($PLUGIN_HOOKS['plugin_pdf'] as $type => $typepdf) {
      CommonGLPI::registerStandardTab($type, $typepdf);
   }
}


function plugin_pdf_MassiveActions($type) {
   global $PLUGIN_HOOKS;

   switch ($type) {
      case 'Profile' :
         return array("plugin_pdf_allow" => __('Print to pdf', 'pdf'));

      default :
         if (isset($PLUGIN_HOOKS['plugin_pdf'][$type])) {
            return array("plugin_pdf_DoIt" => __('Print to pdf', 'pdf'));
         }
   }
   return array();
}


function plugin_pdf_MassiveActionsDisplay($options=array()) {
   global $PLUGIN_HOOKS;

   switch ($options['itemtype']) {
      case 'Profile' :
         switch ($options['action']) {
            case "plugin_pdf_allow":
               Dropdown::showYesNo('use');
               echo "<input type='submit' name='massiveaction' class='submit' value='".
                     _sx('button', 'Post')."'>";
               break;
         }
         break;

      default :
         if (isset($PLUGIN_HOOKS['plugin_pdf'][$options['itemtype']]) && $options['action']=='plugin_pdf_DoIt') {
            echo "<input type='submit' name='massiveaction' class='submit' value='".
                   _sx('button', 'Post')."'>";
         }
   }
   return "";
}


function plugin_pdf_MassiveActionsProcess($data){

   switch ($data["action"]) {
      case "plugin_pdf_DoIt" :
         foreach ($data['item'] as $key => $val) {
            if ($val) {
               $tab_id[]=$key;
            }
         }
         $_SESSION["plugin_pdf"]["type"]   = $data["itemtype"];
         $_SESSION["plugin_pdf"]["tab_id"] = serialize($tab_id);
         echo "<script type='text/javascript'>
               location.href='../plugins/pdf/front/export.massive.php'</script>";
         break;

      case "plugin_pdf_allow" :
         $profglpi = new Profile();
         $prof     = new PluginPdfProfile();
         foreach ($data['item'] as $key => $val) {
            if ($profglpi->getFromDB($key) && $profglpi->fields['interface']!='helpdesk') {
               if ($prof->getFromDB($key)) {
                  $prof->update(array('id'  => $key,
                                      'use' => $data['use']));
               } else if ($data['use']) {
                  $prof->add(array('id' => $key,
                                   'use' => $data['use']));
               }
            }
         }
         break;
   }
}


function plugin_pdf_install() {
   global $DB;

   $migration = new Migration('0.84');
   if (!TableExists('glpi_plugin_pdf_profiles')) {
      ProfileRight::addProfileRights(array('plugin_pdf'));
   } else {
      if (FieldExists('glpi_plugin_pdf_profiles','ID')) { //< 0.7.0
         $migration->changeField('glpi_plugin_pdf_profiles', 'ID', 'id', 'autoincrement');
      }
      // -- SINCE 0.85 --
      //Add new rights in glpi_profilerights table
      $profileRight = new ProfileRight();
      $query = "SELECT *
                FROM `glpi_plugin_pdf_profiles`
                WHERE `use` = 1";

      foreach ($DB->request($query) as $data) {
         $right['profiles_id']   = $data['id'];
         $right['name']          = "plugin_pdf";
         $right['rights']        = $data['use'];

         $profileRight->add($right);
      }
      $DB->query("DROP TABLE `glpi_plugin_pdf_profiles`");

   }

   if (!TableExists('glpi_plugin_pdf_preference')) {
      $query= "CREATE TABLE IF NOT EXISTS
               `glpi_plugin_pdf_preferences` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `users_id` int(11) NOT NULL COMMENT 'RELATION to glpi_users (id)',
                  `itemtype` VARCHAR(100) NOT NULL COMMENT 'see define.php *_TYPE constant',
                  `tabref` varchar(255) NOT NULL COMMENT 'ref of tab to display, or plugname_#, or option name',
                  PRIMARY KEY (`id`)
               ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->query($query) or die($DB->error());
   } else {
      $migration->renameTable('glpi_plugin_pdf_preference', 'glpi_plugin_pdf_preferences');

      // 0.6.0
      if (FieldExists('glpi_plugin_pdf_preferences','user_id')) {
         $migration->changeField('glpi_plugin_pdf_preferences', 'user_id', 'users_id', 'integer',
                                 array('comment' => 'RELATION to glpi_users (id)'));
      }
      // 0.6.1
      if (FieldExists('glpi_plugin_pdf_preferences','FK_users')) {
         $migration->changeField('glpi_plugin_pdf_preferences', 'FK_users', 'users_id', 'integer',
                                 array('comment' => 'RELATION to glpi_users (id)'));
      }
      // 0.6.0
      if (FieldExists('glpi_plugin_pdf_preferences','cat')) {
         $migration->changeField('glpi_plugin_pdf_preferences', 'cat', 'itemtype',
                                 'VARCHAR(100) NOT NULL',
                                 array('comment' => 'see define.php *_TYPE constant'));
      }
      // 0.6.1
      if (FieldExists('glpi_plugin_pdf_preferences','device_type')) {
         $migration->changeField('glpi_plugin_pdf_preferences', 'device_type', 'itemtype',
                                 'VARCHAR(100) NOT NULL',
                                 array('comment' => 'see define.php *_TYPE constant'));
      }
      // 0.6.0
      if (FieldExists('glpi_plugin_pdf_preferences','table_num')) {
         $migration->changeField('glpi_plugin_pdf_preferences', 'table_num', 'tabref',
                                 'string',
                                 array('comment' => 'ref of tab to display, or plugname_#, or option name'));
      }
      $migration->executeMigration();
   }
/*
   // Give right to current Profile
   include_once (GLPI_ROOT . '/plugins/pdf/inc/profile.class.php');
   $prof =  new PluginPdfProfile();
   if (!$prof->getFromDB($_SESSION['glpiactiveprofile']['id'])) {
      $prof->add(array('id'      => $_SESSION['glpiactiveprofile']['id'],
                       'profile' => $_SESSION['glpiactiveprofile']['name'],
                       'use'     => 1));
   }*/
   return true;
}


function plugin_pdf_uninstall() {
   global $DB;

   $tables = array ("glpi_plugin_pdf_preference",
                    "glpi_plugin_pdf_profiles",
                    "glpi_plugin_pdf_preferences");

   $migration = new Migration('0.85');
   foreach ($tables as $table) {
      $migration->dropTable($table);
   }

   //Delete rights associated with the plugin
   $query = "DELETE
             FROM `glpi_profilerights`
             WHERE `name` = 'plugin_pdf'";
   $DB->queryOrDie($query, $DB->error());

   return true;
}
?>
