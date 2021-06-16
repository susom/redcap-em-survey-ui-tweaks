<?php
namespace Stanford\SurveyUITweaks;

include_once ("emLoggerTrait.php");

use \REDCap;
use \Survey;

/**
 *
 * A bunch of little CSS and JS tweaks that help enhance survey functionality
 * TODO: Make configuration available from the actual Survey Settings page
 *
 * Class SurveyUITweaks
 * @package Stanford\SurveyUITweaks
 */
class SurveyUITweaks extends \ExternalModules\AbstractExternalModule
{
    use emLoggerTrait;

    public $settings;   // Per survey subsettings

    public $instances;

    public $global_ui_settings;
    public $instance_ui_settings;

    public $title;

    public $record;

    public $context;    // Args from calling hook function

    const TAG_PREFIX = "survey_ui_tweaks_";
    const CONFIG_UI_ATTR = "enable-survey-settings-ui";


    function __construct()
    {
        parent::__construct();
        if ($this->getProjectId()) {
            // Load the project settings
            // $this->emDebug("In Project Context!");
            // https://github.com/vanderbilt/redcap-external-modules/issues/329
            // $this->settings = $this->framework->getSubSettings('survey_tweaks');
        }
    }

    function loadSettings() {
        // Load all project settings
        if (empty($this->settings) && $this->getProjectId()) {
            $this->settings = $this->getProjectSettings();
            $this->instances = $this->getSubSettings('survey_tweaks');
        }
        // $this->emDebug($this->settings);
    }

    // Build two arrays for global and per-survey tweaks that are enabled for UI editing in survey settings
    function loadSettingsUITweaks()
    {
        // Get the EM config:
        $config = $this->getConfig();

        // Array of global configuration settings where key is global setting name
        $global_ui_settings = [];

        // Build array for survey_tweak subsettings where key is setting name
        $instance_ui_settings = [];

        // Get the survey tweaks sub-settings
        $survey_tweaks = false;
        foreach ($config['project-settings'] as $project_setting) {
            $key = $project_setting['key'];

            if ($key == 'survey_tweaks') {
                foreach ($project_setting['sub_settings'] as $instance_setting) {
                    // Skip those instance settings that are missing the config.js attribute
                    if (!isset($instance_setting[self::CONFIG_UI_ATTR])) continue;

                    $key = $instance_setting['key'];
                    $instance_ui_settings[$key] = $instance_setting;
                }
            } else {
                // This is not the 'special survey ui tweaks' repeating section
                // Skip those instance settings that are missing the config.js attribute
                if (!isset($project_setting[self::CONFIG_UI_ATTR])) continue;
                $global_ui_settings[$key] = $project_setting;
            }
        }

        $this->global_ui_settings = $global_ui_settings;
        $this->instance_ui_settings = $instance_ui_settings;
    }


    # THESE ARE TWEAKS FOR SURVEY_PAGE_TOP
    function redcap_survey_page_top($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {
        $this->loadSettings();

        $this->title = $instrument;
        $survey_page_top_tweaks = array(
            'remove_excess_td'              => 'removeExcessTd',
            'autoscroll'                    => 'autoscroll',
            'hide_queue_corner'             => 'hideQueueCorner',
            'hide_font_resize'              => 'hideFontResize',
            'hide_submit_button'            => 'hideSubmitButton',
            'rename_submit_button'          => 'renameSubmitButton',
            'hide_reset_button'             => 'hideResetButton',
            'rename_next_button'            => 'renameNextButton',
            'rename_previous_button'        => 'renamePreviousButton',
            'hide_required_text'            => 'hideRequiredText',
            'resize_survey'                 => 'resizeSurvey',
            'social_share'                  => 'socialShare',
            'responsive_td_fix'             => 'customTDFix'
        );

        foreach($survey_page_top_tweaks as $key=>$func) {
            $this->checkFeature($key, $func, $instrument);
        }

        $this->checkSurveyDuration($instrument);

        $this->checkMatrixRank($instrument);

        // TODO put recommendation to fix this as part of REDCap pull request.  for now put it in here
        echo "<style>#survey_logo { width:100% !important; height:auto !important; }</style>";
    }


    # TWEAKS FOR EVERY_PAGE_TOP
    function redcap_every_page_top($project_id)
    {
        $this->loadSettings();

        // When editing the survey settings, inject UI configuration for survey settings
        $this->renderSurveySettings();

        // These tweaks do not use the standard survey hooks - so, as a work-around, we use the every_page_top hook
        $every_page_top_tweaks = array();

        // Handle save and return page which doesn't fit under survey_page_top or survey_complete
        if (PAGE === "surveys/index.php" && isset($_GET['__return'])) {
            $every_page_top_tweaks['save_and_return_without_email'] = 'saveAndReturnWithoutEmail';
        }

        foreach($every_page_top_tweaks as $key=>$func) {
            // We do not have an instrument name in this hook so only global hooks are supported
            $this->checkFeature($key, $func, null);
        }
    }

    # HANDLE SAVE OF SURVEY SETTINGS
	function redcap_every_page_before_render($project_id = null)
	{
        // When the survey settings page is saved, we want to pull out the surveyui-tweak params and save them separately
		// Note: that we must use the before_render hook here because the POST handling occurs before the every_page_top is called
		$this->updateSurveySettings();
	}


	# THESE ARE TWEAKS FOR SURVEY_COMPLETE
    function redcap_survey_complete($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {
        $this->loadSettings();

        $this->title = $instrument;
        $this->record = $record;

        $survey_complete_tweaks = array(
            'hide_queue_end'        => 'hideQueueEnd',
            'social_share'          => 'SocialShare',
            'survey_login_on_save'  => 'surveyLoginOnSave'
        );

        foreach($survey_complete_tweaks as $key=>$func) {
            $this->checkFeature($key, $func, $instrument);
        }
    }




	/**
	 * When editing or creating survey settings, we need to insert into the form the option for enabling webauth
	 */
	function renderSurveySettings()
	{
		if (PAGE == "Surveys/edit_info.php" || PAGE == "Surveys/create_survey.php") {
			$survey_name = $_GET['page'];

			//$this->emDebug(__FUNCTION__, $this->settings);

            // Get per-survey settings
            $survey_settings = false;
            foreach ($this->instances as $i => $instance) {
                if ($instance['survey_name'] == $survey_name) {
                    $survey_settings = $instance;
                    break;
                }
            }

            // Pre-populate the config setting arrays that we include in the survey settings
            $this->loadSettingsUITweaks();

            //
            // // Get the EM config:
            // $config = $this->getConfig();
            //
            // // Build array with project settings
            // $project_settings = [];
            //
            // // Array of global configuration settings where key is global setting name
            // $global_settings = [];
            //
            // // Build array for survey_tweak subsettings where key is setting name
            // $instance_settings = [];
            //
            // // Get the survey tweaks sub-settings
            // $survey_tweaks = false;
            // foreach ($config['project-settings'] as $project_setting) {
            //     $key = $project_setting['key'];
            //     if ($key == 'survey_tweaks') {
            //         foreach ($project_settings['survey_tweaks']['sub_settings'] as $instance_setting) {
            //             if (!isset($instance_setting[self::CONFIG_UI_ATTR])) continue;
            //             $key = $instance_setting['key'];
            //             $instance_settings[$key] = $instance_setting;
            //         }
            //     } else {
            //         if (!isset($config[self::CONFIG_UI_ATTR])) continue;
            //
            //     }
            //
            //
            //     $project_settings[$key] = $project_setting;
            //     if (strpos($key, "global_") === 0) {
            //         $global_settings[$key] = $project_setting;
            //     }
            // }
            // //$this->emDebug($project_settings);


            // Render GLOBAL Settings:
            ?>
            <div id='survey_ui_global_tweaks_holder' style="display:none;">
                <table>
            <?php
            foreach ($this->global_ui_settings as $key => $tweak) {
                $value = $this->settings[$key];
                $instance_input = str_replace($key, "global_", "");
                $this->renderInput($tweak, $value, $instance_input);
            }
            ?>
                </table>
            </div>
            <?php
            $this->renderJsMover("Global Survey UI Tweak Settings", "survey_ui_global_tweaks_holder");


            // Render per-form settings
            ?>
            <div id='survey_ui_tweaks_holder' style="display:none;">
                <table>
            <?php
            foreach ($this->instance_ui_settings as $key => $tweak) {
                $value = $survey_settings[$key];
                $this->renderInput($tweak, $value);
            }
            ?>
                </table>
            </div>
            <?php
            $this->renderJsMover("Survey UI Tweaks for This Survey Only", "survey_ui_tweaks_holder");
		}
	}

    //survey_ui_tweaks_holder
	function renderJsMover($header, $table_id) {
        ?>
           <script>
                $(document).ready(function () {
                    let sa = $("div.header:contains('Survey Access:')").parent().parent();
                    console.log("SA", sa);
                    let ui = $('<tr><td colspan="3"><div class="header" style="padding:7px 10px 5px;margin:0 -7px 10px;">' +
                        '<?php echo $header ?>:</div></td></tr>');
                    console.log("UI", ui);
                    ui.insertBefore(sa);
                    let last = ui;
                    $('tr', '#<?php echo $table_id ?>').each(function(i,e){
                        console.log(i,e);
                        $(e).insertAfter(last);
                        last = $(e);
                    });
                });
            </script>
        <?php
	}

    function renderInput($tweak, $value, $instance_data = null) {
        $key = $tweak['key'];
	    $tag_name = self::TAG_PREFIX . $key;
	    $type = $tweak['type'];

	    $input = "";
	    switch ($type) {
	        case "checkbox":
	            $input = "<label for='$tag_name'>Enable: </label><input type='checkbox' data-instance='$instance_data' name='$tag_name'" . ($value ? 'checked' : '') . ">";
	            break;
            case "text":
                $input = "<input type='text' data-instance='$instance_data' name='$tag_name' value='$value' />";
                break;
            default:
                //$this->emDebug("Invalid type: $type", $tweak);
	    }
        if (!empty($input)) {
            $tr = [];
            $tr[] = "<tr id='$tag_name-tr'>";
            $tr[] = "<td valign='top' style='width:20px;'><i class='fa fa-search-plus' /></td>";
            $tr[] = "<td valign='top' style='width:290px;padding-bottom:15px;'>";
            $tr[] = $tweak['name'];
            $tr[] = "</td>";
            $tr[] = "<td valign='top' style='padding:0 0 10px 15px;'>";
            $tr[] = $input;
            $tr[] = "</td>";
            $tr[] = "</tr>";
            echo implode("\n", $tr);
        }
    }

	/**
	 * When a survey settings are saved, update the external module based on the form settings
	 */
	function updateSurveySettings()
	{
		if ((PAGE == "Surveys/edit_info.php" || PAGE == "Surveys/create_survey.php") && $_SERVER['REQUEST_METHOD'] == 'POST') {
            $survey_name = $_GET['page'];

            // Build a sub-array of the post for those settings that are survey_ui-related
            $tweaks = [];
            foreach ($_POST as $k => $v) {
                if (strpos($k, self::TAG_PREFIX) === 0) {
                    unset($_POST[$k]);
                    $tweaks[str_replace(self::TAG_PREFIX, "", $k)] = $v;
                }
            }

            // Get Index of Current Page
            $this->loadSettings();
            $instance_index = null;
            foreach ($this->instances as $i => $instance) {
                if ($instance['survey_name'] == $survey_name) {
                    $instance_index = $i;
                    break;
                }
            }

            // Prepopulate the config setting arrays that we include in the survey settings
            $this->loadSettingsUITweaks();

            // Loop through the settings:
            foreach (array_keys($this->global_ui_settings) as $k) {
                $currentValue = $this->settings[$k];

                // Get the post value for this key if it exists
                if (isset($tweaks[$k])) {
                    $saveValue = $tweaks[$k] == "on" ? true : $tweaks[$k];
                } else {
                    $saveValue = null;
                }

                if (!empty($currentValue) && empty($saveValue)) {
                    // Was a value unchceked or cleared?
                    $this->emDebug("$currentValue was cleared");
                    $this->settings[$k] = null;
                } elseif (!empty($saveValue)) {
                    // Was the value changed or set
                    if ($saveValue !== $currentValue) {
                        $this->emDebug("$k was updated from", $currentValue, $saveValue);
                        $this->settings[$k] = $saveValue;
                    }
                }
            }

            foreach (array_keys($this->instance_ui_settings) as $k) {
                // Get the post value for this key if it exists
                if (isset($tweaks[$k])) {
                    $saveValue = $tweaks[$k] == "on" ? true : $tweaks[$k];
                } else {
                    $saveValue = null;
                }

                // Skip if instance isn't here
                if (!isset($this->settings[$k][$instance_index])) continue;

                // Was a value unchecked or cleared?
                $currentValue = $this->settings[$k][$instance_index];
                if (!empty($currentValue) && empty($saveValue)) {
                    $this->emDebug("$k for $survey_name was cleared from $currentValue to nothing");
                    $this->settings[$k][$instance_index] = null;
                } elseif (!empty($saveValue)) {
                    // Was the value changed or set
                    if ($saveValue !== $currentValue) {
                        $this->emDebug("$k for $survey_name was updated from $currentValue to $saveValue");
                        $this->settings[$k][$instance_index] = $saveValue;
                    }
                }
            }

            // Parse out parameters from POST
			// $this->emDebug($survey_name,$tweaks);
			$this->emDebug($this->settings);
            $this->setProjectSettings($this->settings);

		}
	}





    ## ACTUAL TWEAK FUNCTIONS - ADD MORE TO YOUR HEART'S CONTENT!

    /**
     * This will do a login when the specified survey is saved.
     */
    function surveyLoginOnSave() {

        $project_id = $this->getProjectId();
        global $password_algo, $salt;
        $record = $this->record;

        // Skip if surveyLogin is not enabled
        if (! Survey::surveyLoginEnabled()) return;

        // Add cookie to preserve the respondent's login "session" across multiple surveys in a project
        setcookie('survey_login_pid'.$project_id, hash($password_algo, "$project_id|$record|$salt"),
                  time()+(Survey::getSurveyLoginAutoLogoutTimer()*60), '/', '', false, true);
        // Add second cookie that expires when the browser is closed (BOTH cookies must exist to auto-login respondent)
        setcookie('survey_login_session_pid'.$project_id, hash($password_algo, "$project_id|$record|$salt"), 0, '/', '', false, true);

    }

    function checkSurveyDuration($instrument) {
        // Array of arrays of duration_field => field_name
        $duration_fields = array();
        $duration_field_settings = $this->getSubSettings('survey_duration_fields');
        foreach ($duration_field_settings as $setting) {
            $duration_fields[] = $setting['duration_field'];
        }

        $metadata = REDCap::getDataDictionary('array',false,null,$instrument,false);
        foreach ($metadata as $field_name=>$field_attributes) {
            if (!in_array($field_name, $duration_fields) && preg_match('/@SURVEY-?DURATION(?=\s|$)/', $field_attributes['field_annotation'])) {
                $duration_fields[] = $field_name;
            }
        }

        // Dont do anything if we don't have any duration fields specified
        if (empty($duration_fields)) return false;

        // Filter duration fields by instrument
        $instrument_fields = REDCap::getFieldNames($instrument);
        $fields = array_intersect($duration_fields, $instrument_fields);

        // None of the fields are on the current form
        if (empty($fields)) return false;

        ?>
            <script>
                var SurveyDuration = SurveyDuration || {};
                SurveyDuration.fields = <?php echo json_encode($fields) ?>;
                <?php echo file_get_contents($this->getModulePath() . "js/surveyduration.js"); ?>
            </script>
        <?php
    }

    ## ACTUAL TWEAK FUNCTIONS - ADD MORE TO YOUR HEART'S CONTENT!
    function checkMatrixRank($instrument) {
        // Array of arrays of matrix_groups => field_name
        $matrix_groups  = array();
        $matrix_options = array();
        $field_settings = $this->getSubSettings('sortrank');

        foreach ($field_settings as $setting) {
            $matrix_groups[]                            = $setting['matrix_name'];
            $matrix_options[$setting['matrix_name']]    = array(
                 'show_rank_label'      => $setting['show_rank_label']
                ,'matrix_instructions'  => $setting['matrix_instructions']
                ,'randomize_options'    => $setting['randomize_options']
            );
        }

        // Dont do anything if we don't have any matrix_groups specified
        if (empty($matrix_groups)) return false;

        // Get Instrument Data Dictionary
        $instrument_dict_json =  REDCap::getDataDictionary($this->getProjectId(), "json", False, Null, array($instrument));
        $instrument_dict =  json_decode($instrument_dict_json,true);

        // IF Survey in Progress Will need to Get State... Scrape from existing?
        // Or Download here and redo... seems wasteful to do a duplicate

        $error_arr  = array();
        $matrix_arr = array();
        foreach($instrument_dict as $field){
            $mtx_grp = $field["matrix_group_name"];

            if(!empty($mtx_grp) && in_array($mtx_grp, $matrix_groups)){
                //Iterate through data dict. And look for Desired Matrix Ranks
                if(!array_key_exists($mtx_grp, $error_arr)) $error_arr[$mtx_grp] = array();

                //Verify field type = radio
                //Verify matrix_ranking = y
                if(empty($error_arr[$mtx_grp])) {
                    if ($field["field_type"] !== "radio") {
                        $error_arr[$mtx_grp][] = "field_type must be 'radio'";
                    }
                    if ($field["matrix_ranking"] !== "y") {
                        $error_arr[$mtx_grp][] = "matrix_ranking must be 'y'";
                    }
                }

                if(empty($error_arr[$mtx_grp])){
                    if(!array_key_exists($mtx_grp, $matrix_arr)) $matrix_arr[$mtx_grp] = array();
                    $matrix_arr[$mtx_grp][] = $field;
                }
            }
        }
        //LOG ANY ERRORS
        if(!empty($error_arr)){
            foreach($error_arr as $err_mtx_grp => $errs){
                if(!empty($errs)){
                    REDCap::logEvent("[". $this->PREFIX . "] Invalid Rank Matrix Config", "$err_mtx_grp : " . implode(", ", $errs));
                }
            }
        }

        // None of the matrix_groups are on the current form
        if (empty($matrix_arr)) return false;

        $rank_matrices = array();
        foreach($matrix_arr as $mtx_grp => $fields){
            $rank_matrices[$mtx_grp] = array();

            $field_names    = array();
            $field_labels   = array();
            foreach($fields as $field){
                $field_names[]  = $field["field_name"];
                $field_labels[] = $field["field_label"];
            }

            $choice_split = explode(" | ", $fields[0]["select_choices_or_calculations"]);
            $choice_arr = array();
            foreach($choice_split as $split){
                list($k,$v) = explode(",",$split, 2);
                $choice_arr[trim($k)] = trim($v);
            }

            $rank_matrices[$mtx_grp]["choices"] = $choice_arr;
            $rank_matrices[$mtx_grp]["header"]  = $fields[0]["section_header"];
            $rank_matrices[$mtx_grp]["names"]   = $field_names;
            $rank_matrices[$mtx_grp]["labels"]  = $field_labels;
            $rank_matrices[$mtx_grp]["options"] = $matrix_options[$mtx_grp];
        }


//        $this->emDebug($rank_matrices);

        ?>
        <style>
            .sort_rank_container {
                padding:2vh;
                overflow:hidden;
            }
            .draggable{
                display:inline-block;
                float:left;
                border:1px solid #efefef;
                border-radius:5px;
                padding:.25%;
                width:48%;
            }
            .draggable:last-child{
                margin-left:2%;
            }
            .draggable ul{
                margin:0;
                padding:0;
                list-style:none;
                position:relative;
                z-index:1;
            }
            .draggable li{
                display: block;
                border: 1px solid #ccc;
                margin: 1vh;
                padding: 2vh;
                border-radius: 5px;
                cursor: pointer;
                min-height: 2vh;
                background:#fff;
            }
            .draggable li:hover{
                background:#efefef;
            }
            .draggable p.alert{
                border:1px dashed #ccc !important;
                margin:20px;
                position:absolute;
                z-index:0;
            }
            .draggable li.branch_hidden{
                display:none;
                border:1px solid red;
            }
            .show_overide {
                display:table-row !important;
            }
        </style>
        <script>
            var MatrixRanking       = MatrixRanking || {};
            MatrixRanking.config    = <?php echo json_encode($rank_matrices) ?>;
            <?php
                //THE ORDER OF THE FOLLOWING 3 JS files MUST BE LIKE SO.
            echo file_get_contents($this->getModulePath() . "js/sortable.min.js");
                echo file_get_contents($this->getModulePath() . "js/jquery-sortable.js");
            echo file_get_contents($this->getModulePath() . "js/matrixranking.js");
            ?>
        </script>
        <?php
    }

    function removeExcessTd()
    {
        //remove the excess TD on left if $question_auto_numbering on
        global $question_auto_numbering;
        if ($question_auto_numbering == 0) {
            ?>
            <style>
                td.questionnum, td.questionnummatrix {
                    display: none !important;
                }

                /* When we clean up the left space, we can extend enhanced choice options
                to be full width for a better appearance */
                div.enhancedchoice label { width: 100% }

            </style>
            <?php
        }
    }

    function hideSubmitButton()
    {
        // TODO: Change to CSS fix instead of JS
        ?>
            <style>
                tr.surveysubmit {
                    opacity: 0;
                }
            </style>
            <script type="text/javascript">
                $(document).ready(function () {
                    $("button:contains('Submit')").hide();
                    $("tr.surveysubmit").css({"opacity":1});
                });
            </script>
        <?php
    }

    function hideQueueCorner()
    {
        ?>
        <style>
            #return_corner, #survey_queue_corner {
            display: none !important;
                    }
        </style>
        <?php
    }

    function hideFontResize()
    {
        ?>
        <style>
            #changeFont .nowrap {
                display: none;
            }
        </style>
        <?php
    }

    function autoscroll()
    {
        ?>
        <style>
            #autoscroll         { background-color: #666; display:inline-block; color: #fff !important; }
            #autoscroll.enabled { background-color: #8C1515; }
        </style>
        <?php
        echo "<script>" . file_get_contents($this->getModulePath() . "/js/autoscroll.js") . "</script>";
    }

    function socialShare(){
        // WHEN WE UPDATE TO FONTAWESOME 5, THEN CAN STOP USING THE HARD IMAGES, AND JUST USE THE FONT CALL
        ?>
        <style>
            #media_share { text-align:right; padding:5px 10px 10px; }
            #media_share .fa {
                font-size:18px;
                vertical-align: baseline;
                margin-left:3px;
                width:19px; height:19px;
            }
            #media_share .fa-facebook{
                background:url(<?php echo $this->getUrl('img/icon_social_media.png',true,true ) ?>) top left no-repeat;
                background-size:300%;
            }
            #media_share .fa-twitter{
                background:url(<?php echo $this->getUrl('img/icon_social_media.png',true,true ) ?>) top right no-repeat;
                background-size:300%;
            }
            #media_share .fa-linkedin{
                background:url(<?php echo $this->getUrl('img/icon_social_media.png',true,true ) ?>) -19px 0 no-repeat;
                background-size:300%;
            }
            #media_share .fa-facebook:before,
            #media_share .fa-linkedin:before,
            #media_share .fa-twitter:before{
                visibility:hidden;
            }
        </style>
        <?php
        $show_social_share_title    = $this->getProjectSetting("global_social_share_title");
        $pretty_title               = ucwords(str_replace("_", " ", $this->title));  //TODO theres a redcap function or var for this already somwher
        $survey_url                 = $this->getPublicSurveyUrl();

        $html   = "<div id='media_share'>";
        $html   .= "<span>Share :</span>";

        // TODO, BETTER WAY TO CURATE THIS FONTAWESOME LIST?
        $social_shares = array("envelope", "facebook", "twitter", "linkedin");
        foreach($social_shares as $key => $icon){
            $project_title  = !empty($show_social_share_title) ? $show_social_share_title . " " . $pretty_title : $pretty_title;
            switch($icon){
                case "envelope":
                    $href   = "mailto:?Subject=" . $project_title . "&amp;Body=" . $survey_url;
                    break;

                case "facebook":
                    $href   = "http://www.facebook.com/sharer.php?s=100&p%5burl%5d=" . $survey_url;
                    break;

                case "twitter":
                    $href   = "https://twitter.com/share?url=" . $survey_url;
                    break;

                case "linkedin":
                    $href   = "http://www.linkedin.com/shareArticle?mini=true&url=".$survey_url."&title=" . $project_title;
                    break;

                default :
                    $href   = "#"; //TODO ??
                    break;
            }

            $media  = $icon == "envelope" ? "email" : $icon;
            $title  = "Share via $media";
            $html .= "<a title='$title' href='$href'><i class='fa fa-$icon'></i></a>";
        }
        $html .= "</div>";
        ?>
        <script>
        $(document).ready(function(){
            var insertHTML = $("<?php echo $html ?>");
            insertHTML.insertBefore($("#pagecontent"));
        });
        </script>
        <?php
    }

    function customTDFix(){
        ?>
        <style>
            @media only screen and (max-width: 600px) {
                #survey_logo {
                    width:100% !important;
                    height:auto !important;
                }
                #questiontable td {
                    width:100%;
                    display:block !important;
                    box-sizing:border-box;
                    clear:both ;
                    max-width:initial !important;
                    flex: auto !important;
                }
            }
        </style>
        <script>
            $("#pagecontainer").attr("style","max-width:100% !important;");
        </script>
        <?php
    }

    function renameSubmitButton($name)
    {
        global $lang;
        ?>
        <style>
            tr.surveysubmit {
                opacity: 0;

            }
        </style>
        <script type="text/javascript">
            $(document).ready(function () {
                var newval = "<?php echo $name ?>";
                $("button:contains(<?php echo $lang['survey_200']; ?>)").text(newval);
                $("tr.surveysubmit").css({"opacity": 1});

                if($("button:contains(newval)")){
                    $('[name = "submit-btn-saverecord"]').attr('style', 'min-width: 140px; color: #800000; width: 100%; padding-left: 10px !important; padding-right: 10px !important; white-space: initial !important; overflow-wrap: break-word !important');
                }
            });
        </script>
        <?php
    }

    function renameNextButton($name)
    {
        global $lang;
        ?>
        <style>
            tr.surveysubmit{
                opacity: 0;
            }
        </style>
        <script type = "text/javascript">
            $(document).ready(function () {
                var newval = "<?php echo $name ?>";
                $("button:contains(<?php echo $lang['data_entry_213']; ?>)").text(newval);
                $("tr.surveysubmit").css({"opacity": 1});

                if($("button:contains(newval)")) {
                    $('[name = "submit-btn-saverecord"]').attr('style', 'min-width: 140px; color: #800000; width: 100%; padding-left: 10px !important; padding-right: 10px !important; white-space: initial !important; overflow-wrap: break-word !important');
                }
            });
        </script>
        <?php
    }

    function renamePreviousButton($name)
    {
        global $lang;
        ?>
        <style>
            tr.surveysubmit{
                opacity: 0;
            }
        </style>
        <script type = "text/javascript">
            $(document).ready(function () {
                var newval = "<?php echo $name ?>";
                $("button:contains(<?php echo $lang['data_entry_214']; ?>)").text(newval);
                $("tr.surveysubmit").css({"opacity": 1});

                if($("button:contains(newval)")) {
                    $('[name = "submit-btn-saveprevpage"]').attr('style', 'min-width: 140px; color: #800000; width: 100%; padding-left: 10px !important; padding-right: 10px !important; white-space: initial !important; overflow-wrap: break-word !important');
                }
            });
        </script>
        <?php
    }

    // Hide the survey queue summary at the end of survey page
    function hideQueueEnd()
    {
        ?>
            <style>
                #survey_queue {
                    display: none !important;
                }
            </style>
        <?php
    }

    // Hide the reset links for radio questions
    function hideResetButton()
    {
        ?>
        <style>
            .smalllink { display:none !important; }
        </style>
        <script type="text/javascript">
            // $(document).ready(function () {
            //     $(".smalllink").remove();
            // });
        </script>
        <?php
    }

    function hideRequiredText()
    {
        ?>
        <script>
            $(document).ready(function() {
                $(".requiredlabel").text("*");
            });
        </script>
        <?php
    }

    function resizeSurvey($size)
    {
        ?>
        <script type = "text/javascript">
            $(document).ready(function(){
                $("#pagecontainer").attr('style', 'max-width: <?php echo $size?>% !important');
                $("#surveytitlelogo").attr('style', 'max-width: 95% !important');
            });
        </script>

        <?php
    }

    function saveAndReturnWithoutEmail()
    {
        ?>
        <style>
            #return_instructions {display:none;}
        </style>
        <script type = "text/javascript">
            $(document).ready(function(){
                $(document.querySelector("#return_instructions > div > div:nth-child(5)")).remove();
                $(document.querySelector("#return_instructions > div > div:nth-child(4) > span:nth-child(8)")).remove();
                $(document.querySelector("#return-step1")).text('A return code is *required* in order to continue the survey where you left off. Please write down the value listed below as well as as the URL of this page.');

                $('#provideEmail').html("<label>You may bookmark this page to return to the survey or copy this url:</label>" +
                    "<div><code>" + window.location.href + "</code></div>");
                setTimeout(function() {
                    $('#return_instructions').fadeIn();
                }, 550);
            });
        </script>
        <?php
}




    /**
     * A helper that assumes the keyNames for global or survey-specific are the same
     * @param       $keyName        // This is the name of the key for the survey-specific setting (should be checkbox)
     * @param       $funcName       // This is the function to call if true
     * @param       $instrument     // This is the current instrument
     * @param array $args           // This is an optional array of parameters to pass to the function
     *                              // otherwise the return value from the keyName setting is passed to the function
     */
    function checkFeature($keyName, $funcName, $instrument, $args = array())
    {
        $globalKey          = 'global_' . $keyName;

        $this->emDebug("Project Settings", $this->settings);

        // See if global key exists
        $keyFound           = array_key_exists($globalKey, $this->settings);
        $global_setting     = $this->settings[$globalKey];

        // If globally checked true, then evaluate
        if ($global_setting) {
            // Globally enabled
            $this->emDebug("enabling global $funcName");
            call_user_func_array(array($this, $funcName), empty($args) ? array($global_setting) : $args);
        } else {
            // Not Global, check for instance-specific
            if (!empty($instrument)) {
                foreach ($this->instances as $instance) {
                    if ($instance['survey_name'] == $instrument && !empty($instance[$keyName])) {
                        $this->emDebug("enabling $funcName on $instrument with $keyName");
                        call_user_func_array(array($this, $funcName), empty($args) ? array($instance[$keyName]) : $args);
                    }
                }
            }
        }
        // if (!$keyFound) $this->emError("Unable to find key $keyName in settings");
    }

    // // TODO: Build function to determine which are enabled
    // function getEnabledTweaks() {
    //     foreach($this::SURVEY_PAGE_TOP_TWEAKS as $key=>$func) {
    //         if ($this->getProjectSetting('global_' . $key)) {
    //         }
    //     }
    // }

}
