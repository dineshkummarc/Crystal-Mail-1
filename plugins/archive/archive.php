<?php

/**
 * Archive
 *
 * Plugin that adds a new button to the mailbox toolbar
 * to move messages to a (user selectable) archive folder.
 *
 * @version @package_version@
 * @author Andre Rodier, Thomas Bruederli
 */
class archive extends crystal_plugin
{
  public $task = 'mail|settings';

  function init()
  {
    $cmail = cmail::get_instance();

    $this->register_action('plugin.archive', array($this, 'request_action'));

    // There is no "Archived flags"
    // $GLOBALS['IMAP_FLAGS']['ARCHIVED'] = 'Archive';
    if ($cmail->task == 'mail' && ($cmail->action == '' || $cmail->action == 'show')
      && ($archive_folder = $cmail->config->get('archive_mbox'))) {
      $skin_path = $this->local_skin_path();
      
      $this->include_script('archive.js');
      $this->add_texts('localization', true);
      $this->add_button(
        array(
            'command' => 'plugin.archive',
            'imagepas' => $skin_path.'/archive_pas.png',
            'imageact' => $skin_path.'/archive_act.png',
            'title' => 'buttontitle',
            'domain' => $this->ID,
        ),
        'toolbar');
      
      // register hook to localize the archive folder
      $this->add_hook('render_mailboxlist', array($this, 'render_mailboxlist'));

      // set env variable for client
      $cmail->output->set_env('archive_folder', $archive_folder);
      $cmail->output->set_env('archive_folder_icon', $this->url($skin_path.'/foldericon.png'));

      // add archive folder to the list of default mailboxes
      if (($default_folders = $cmail->config->get('default_imap_folders')) && !in_array($archive_folder, $default_folders)) {
        $default_folders[] = $archive_folder;
        $cmail->config->set('default_imap_folders', $default_folders);
      }  
    }
    else if ($cmail->task == 'settings') {
      $dont_override = $cmail->config->get('dont_override', array());
      if (!in_array('archive_mbox', $dont_override)) {
        $this->add_hook('user_preferences', array($this, 'prefs_table'));
        $this->add_hook('save_preferences', array($this, 'save_prefs'));
      }
    }
  }
  
  function render_mailboxlist($p)
  {
    $cmail = cmail::get_instance();
    $archive_folder = $cmail->config->get('archive_mbox');

    // set localized name for the configured archive folder
    if ($archive_folder) {
      if (isset($p['list'][$archive_folder]))
        $p['list'][$archive_folder]['name'] = $this->gettext('archivefolder');
      else // search in subfolders
        $this->_mod_folder_name($p['list'], $archive_folder, $this->gettext('archivefolder'));
    }

    return $p;
  }

  function _mod_folder_name(&$list, $folder, $new_name)
  {
    foreach ($list as $idx => $item) {
      if ($item['id'] == $folder) {
        $list[$idx]['name'] = $new_name;
	return true;
      } else if (!empty($item['folders']))
        if ($this->_mod_folder_name($list[$idx]['folders'], $folder, $new_name))
	  return true;
    }
    return false;
  }

  function request_action()
  {
    $this->add_texts('localization');
    
    $uids = get_input_value('_uid', crystal_INPUT_POST);
    $mbox = get_input_value('_mbox', crystal_INPUT_POST);
    
    $cmail = cmail::get_instance();
    
    // There is no "Archive flags", but I left this line in case it may be useful
    // $cmail->imap->set_flag($uids, 'ARCHIVE');
    
    if (($archive_mbox = $cmail->config->get('archive_mbox')) && $mbox != $archive_mbox) {
      $cmail->output->command('move_messages', $archive_mbox);
      $cmail->output->command('display_message', $this->gettext('archived'), 'confirmation');
    }
    
    $cmail->output->send();
  }

  function prefs_table($args)
  {
    global $CURR_SECTION;

    if ($args['section'] == 'folders') {
      $this->add_texts('localization');

      $cmail = cmail::get_instance();

      // load folders list when needed
      if ($CURR_SECTION)
        $select = cmail_mailbox_select(array('noselection' => '---', 'realnames' => true,
          'maxlength' => 30, 'exceptions' => array('INBOX')));
      else
        $select = new html_select();

      $args['blocks']['main']['options']['archive_mbox'] = array(
          'title' => $this->gettext('archivefolder'),
          'content' => $select->show($cmail->config->get('archive_mbox'), array('name' => "_archive_mbox"))
      );
    }

    return $args;
  }

  function save_prefs($args)
  {
    if ($args['section'] == 'folders') {
      $args['prefs']['archive_mbox'] = get_input_value('_archive_mbox', crystal_INPUT_POST);
      return $args;
    }
  }

}
