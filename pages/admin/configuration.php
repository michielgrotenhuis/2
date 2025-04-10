<?php if(!defined("CORE_FOLDER")) return false; ?>

<form action="<?php echo $area_link; ?>" method="post" id="blackwallConfigForm">
    <input type="hidden" name="operation" value="module_controller">
    <input type="hidden" name="module" value="<?php echo $m_name; ?>">
    <input type="hidden" name="controller" value="save">

    <div class="formcon">
        <div class="yuzde30"><?php echo $lang["api_key"]; ?></div>
        <div class="yuzde70">
            <input type="text" name="api_key" value="<?php echo $config["settings"]["api_key"]; ?>">
            <span class="kinfo"><?php echo $lang["api_key_desc"]; ?></span>
        </div>
    </div>

    <div class="formcon">
        <div class="yuzde30"><?php echo $lang["primary_server"]; ?></div>
        <div class="yuzde70">
            <input type="text" name="primary_server" value="<?php echo $config["settings"]["primary_server"]; ?>">
            <span class="kinfo"><?php echo $lang["primary_server_desc"]; ?></span>
        </div>
    </div>

    <div class="formcon">
        <div class="yuzde30"><?php echo $lang["secondary_server"]; ?></div>
        <div class="yuzde70">
            <input type="text" name="secondary_server" value="<?php echo $config["settings"]["secondary_server"]; ?>">
            <span class="kinfo"><?php echo $lang["secondary_server_desc"]; ?></span>
        </div>
    </div>

    <div class="clear"></div>
    <br>

    <div style="float:right;" class="guncellebtn yuzde30">
        <a id="blackwall_settings_submit" href="javascript:void(0);" class="yesilbtn gonderbtn"><?php echo ___("needs/button-save"); ?></a>
    </div>
</form>

<script type="text/javascript">
    $(document).ready(function(){
        $("#blackwall_settings_submit").click(function(){
            MioAjaxElement($(this),{
                waiting_text: waiting_text,
                progress_text: progress_text,
                result:"blackwall_settings_handler",
                form: $("#blackwallConfigForm")
            });
        });
    });
    
    function blackwall_settings_handler(result){
        if(result != ''){
            var solve = getJson(result);
            if(solve !== false){
                if(solve.status == "error"){
                    if(solve.message != undefined && solve.message != '')
                        alert_error(solve.message,{timer:5000});
                }else if(solve.status == "successful")
                    alert_success(solve.message,{timer:2500});
            }else
                console.log(result);
        }
    }
</script>
