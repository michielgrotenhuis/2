<?php
    $LANG           = $module->lang;
    $options        = $order["options"];
    $config         = isset($options["config"]) ? $options["config"] : [];
    $buttons        = $module->adminArea_buttons_output();
?>

<?php
    if($buttons){
        ?>
        <div class="formcon">
            <?php echo $buttons; ?>
        </div>
        <div class="clear"></div>
        <?php
    }
?>
    <div class="clear"></div>

<?php
    if(method_exists($module,"adminArea_service_fields") && $config_options = $module->adminArea_service_fields())
        $module->config_options_output($config_options);
?>

<?php
    $domain = isset($config["blackwall_domain"]) ? $config["blackwall_domain"] : false;
    if($domain):
?>
<div class="formcon">
    <div class="yuzde30">Blackwall UI</div>
    <div class="yuzde70">
        <iframe src="https://apiv2.botguard.net/en/website/<?php echo $domain; ?>/statistics?api-key=<?php echo $module->config["settings"]["api_key"]; ?>" width="100%" height="600" style="border:none;"></iframe>
    </div>
</div>
<?php endif; ?>
