<?php
namespace Stanford\SurveyUITweaks;

include_once ("emLoggerTrait.php");

class SurveyUITweaks extends \ExternalModules\AbstractExternalModule
{
    use emLoggerTrait;

    public $settings;

    function __construct()
    {
        parent::__construct();

        if ($this->getProjectId()) {
            // Load the project settings
            $this->settings = $this->getSubSettings('survey_tweaks');
        }
    }


    ## THESE ARE TWEAKS FOR SURVEY_PAGE_TOP
    function redcap_survey_page_top($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {
        // Remove excess td
        $this->checkFeature('remove_excess_td', 'removeExcessTd', $instrument);

        // Auto scrolling
        $this->checkFeature('autoscroll', 'autoscroll', $instrument);

        // Hide survey queue button
        $this->checkFeature('hide_queue_corner', 'hideQueueCorner', $instrument);

        // Hide font resize button
        $this->checkFeature('hide_font_resize', 'hideFontResize', $instrument);

        // Hide submit button
        $this->checkFeature('hide_submit_button', 'hideSubmitButton', $instrument);

        // Rename submit button
        $this->checkFeature('rename_submit_button', 'renameSubmitButton', $instrument);

        // hide reset button
        $this->checkFeature('hide_reset_button', 'hideResetButton', $instrument);

    }

    ## THESE ARE TWEAKS FOR SURVEY_COMPLETE
    function redcap_survey_complete($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {
        // hide queue summary at end of survey page
        $this->checkFeature('hide_end_queue', 'hideEndQueue', $instrument);
    }


    ## ACTUAL UTILITY FUNCTIONS - ADD MORE TO YOUR HEARTS CONTENT!

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
                    $("[name=submit-btn-saverecord]").hide();
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
            #changeFont {
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


    function renameSubmitButton($name)
    {
        ?>
        <style>
            tr.surveysubmit {
                opacity: 0;
            }
        </style>
        <script type="text/javascript">
            $(document).ready(function () {
                var newval = "<?php echo $name ?>";
                $("button:contains('Submit')").text(newval);
                $("tr.surveysubmit").css({"opacity": 1});
            });
        </script>
        <?php
    }


    // Hide the survey queue summary at the end of survey page
    function hideEndQueue()
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
        $global_setting = $this->getProjectSetting("global_" . $keyName);

        if ($global_setting) {
            $this->emDebug("enabling global $funcName");
            call_user_func_array(array($this, $funcName), empty($args) ? $global_setting : $args);
        } else {
            foreach ($this->settings as $settings) {
                if ($settings['survey_name'] == $instrument && $settings[$keyName]) {
                    $this->emDebug("enabling  $funcName on $instrument");
                    call_user_func_array(array($this, $funcName), empty($args) ? $settings[$keyName] : $args);
                }
            }
        }
    }

}