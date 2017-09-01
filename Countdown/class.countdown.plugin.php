<?php

if (!defined('APPLICATION'))
    exit();

// Define the plugin:
$PluginInfo['Countdown'] = array(
    'Name' => 'Countdown',
    'Description' => 'Add a countdown to a specific time and date to a comment. Based on a plugin written by Matt Sephton.',
    'Version' => '1.3.0',
    'Author' => "Caylus",
    'AuthorUrl' => 'https://open.vanillaforums.com/profile/Caylus',
    'License' => 'GPL v2',
    'SettingsUrl' => '/settings/countdown',
    'SettingsPermission' => 'Garden.Settings.Manage',
    'RequiredApplications' => array('Vanilla' => '>=2')
);

class Countdown extends Gdn_Plugin {

    // settings
    public function SettingsController_Countdown_Create($Sender, $Args = array()) {
        $Sender->Permission('Garden.Settings.Manage');
        $Sender->SetData('Title', T('Countdown'));

        $tzlist[] = DateTimeZone::listIdentifiers();
        $timezones = array_combine($tzlist[0], $tzlist[0]);

        $Cf = new ConfigurationModule($Sender);
        $Cf->Initialize(array(
            'Plugins.Countdown.Tag' => array('Description' => 'The following text will be replaced with the countdown widget', 'Control' => 'TextBox', 'Default' => '[COUNTDOWN]'),
            'Plugins.Countdown.Time' => array('Description' => 'Accepts most English textual date and time descriptions, see <a href="http://php.net/manual/en/function.strtotime.php">strtotime</a>', 'Control' => 'TextBox', 'Default' => '00:00:00 19 August 2012'),
            'Plugins.Countdown.Timezone' => array('Control' => 'DropDown', 'Items' => $timezones, 'Default' => 'UTC'),
            'Plugins.Countdown.Digits' => array('Control' => 'DropDown', 'Items' => array('digits' => 'Original', 'digits_transparent' => 'Original Transparent', 'digits_inverted' => 'Original Transparent Inverted', 'small_transparent' => 'Small Transparent', 'small_inverted' => 'Small Transparent Inverted', 'digits2' => 'LED', 'digits2_blue' => 'LED Blue', 'digits2_green' => 'LED Green', 'digits2_orange' => 'LED Orange', 'digits2_purple' => 'LED Purple', 'digits2_red' => 'LED Red', 'digits2_yellow' => 'LED Yellow'))
        ));

        $Sender->AddSideMenu('dashboard/settings/plugins');
        $Cf->RenderAll();
    }

    // replace in comment
    public function Base_AfterCommentFormat_Handler($Sender) {
        $Object = $Sender->EventArguments['Object'];
        $Object->FormatBody = $this->DoReplacement($Object->FormatBody);
        $Sender->EventArguments['Object'] = $Object;
    }

    public function getTimeFromString($string) {

        $date = new DateTime($string, new DateTimeZone(c('Plugins.Countdown.Timezone', 'UTC')));
        // get seconds
        $CountdownTime = $date->format('U');
        return $CountdownTime;
    }

    // replacement logic
    public function DoReplacement($Text) {
        $number_replacements_allowed = c('Plugins.Countdown.NumReplacementsPerPost', 10);

        $CountdownTag = C('Plugins.Countdown.Tag', '[COUNTDOWN]');

        $this->replaceCustomCountdowns($Text, $CountdownTag, $number_replacements_allowed);
        $this->replaceGeneralCountdowns($Text, $CountdownTag, $number_replacements_allowed);

        return $Text;
    }

    // hook
    public function DiscussionController_Render_Before($Sender) {
        $this->_CountdownSetup($Sender);
    }

    // setup
    private function _CountdownSetup($Sender) {
        $Sender->AddJsFile('flipclock.min.js', 'plugins/Countdown');
        $Sender->AddJsFile('countdown.js', 'plugins/Countdown');
        $Sender->AddCssFile('flipclock.css', 'plugins/Countdown');
    }

    public function replaceCustomCountdowns(&$Text, $CountdownTag, &$number_replacements_allowed) {

        $offset = strlen("$CountdownTag(");
        $begin = strpos($Text, "$CountdownTag(");
        while (($number_replacements_allowed === true || $number_replacements_allowed-- > 0) && $begin !== false) {
            $end = strpos($Text, ")", $begin + $offset);
            if ($end === false) {
                break;
            }
            $string = substr($Text, $begin + $offset, $end - $begin - $offset);
            $time = $this->getTimeFromString($string);
            $CountdownHTML = getCountdownHTML($time);
            $Text = substr_replace($Text, $CountdownHTML, $begin, $end - $begin + 1);
            $charCountDifference = strlen($CountdownHTML) - $end + $begin;
            $begin = strpos($Text, "$CountdownTag(", $end + $charCountDifference);
        }
    }

    public function getCountdownHTML($time) {

        $CountdownHTML = "<div data-countdown='$time'></div>";
        return $CountdownHTML;
    }

    public function replaceGeneralCountdowns(&$Text, $CountdownTag, &$number_replacements_allowed) {
        // time
        $CountdownTime = (C('Plugins.Countdown.Time')) ? C('Plugins.Countdown.Time') : '00:00:00 19 August 2012';
        $time = $this->getTimeFromString($CountdownTime);
        $CountdownHTML = getCountdownHTML($time);
        if ($number_replacements_allowed === true) {
            return str_replace($CountdownTag, $CountdownHTML, $Text);
        }
        $length_to_replace = strlen($CountdownTag);
        for ($i = 0; $i < $number_replacements_allowed; $i++) {
            $begin = strpos($Text, $CountdownTag);
            if ($begin === false) {
                break;
            } else {
                $Text = substr_replace($Text, $CountdownHTML, $begin, $length_to_replace);
            }
        }
    }

}
