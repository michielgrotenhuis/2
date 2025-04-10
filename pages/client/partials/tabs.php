<!-- Simple Tab System -->
<div id="blackwall-tab-system">
    <!-- Tab buttons -->
    <div class="blackwall-tab-buttons">
        <button class="blackwall-tab-button active" onclick="openBlackwallTab('statistics')"><i class="fa fa-bar-chart"></i> <?php echo $lang["view_statistics"]; ?></button>
        <button class="blackwall-tab-button" onclick="openBlackwallTab('events')"><i class="fa fa-list"></i> <?php echo $lang["view_events"]; ?></button>
        <button class="blackwall-tab-button" onclick="openBlackwallTab('settings')"><i class="fa fa-cog"></i> <?php echo $lang["edit_settings"]; ?></button>
        <button class="blackwall-tab-button" onclick="openBlackwallTab('setup')"><i class="fa fa-wrench"></i> <?php echo $lang["setup_instructions"]; ?></button>
    </div>

    <!-- Tab content -->
    <div id="blackwall-tab-statistics" class="blackwall-tab-content active">
        <div class="blackwall-iframe-header">
            <span>Statistics</span>
            <button onclick="refreshIframe('statistics-iframe')" class="refresh-btn"><i class="fa fa-refresh"></i> Refresh</button>
        </div>
        <iframe id="statistics-iframe" src="https://apiv2.botguard.net/en/website/<?php echo $domain; ?>/statistics?api-key=<?php echo $api_key; ?>" frameborder="0"></iframe>
    </div>

    <div id="blackwall-tab-events" class="blackwall-tab-content">
        <div class="blackwall-iframe-header">
            <span>Events Log</span>
            <button onclick="refreshIframe('events-iframe')" class="refresh-btn"><i class="fa fa-refresh"></i> Refresh</button>
        </div>
        <iframe id="events-iframe" src="https://apiv2.botguard.net/en/website/<?php echo $domain; ?>/events?api-key=<?php echo $api_key; ?>" frameborder="0"></iframe>
    </div>

    <div id="blackwall-tab-settings" class="blackwall-tab-content">
        <div class="blackwall-iframe-header">
            <span>Protection Settings</span>
            <button onclick="refreshIframe('settings-iframe')" class="refresh-btn"><i class="fa fa-refresh"></i> Refresh</button>
        </div>
        <iframe id="settings-iframe" src="https://apiv2.botguard.net/en/website/<?php echo $domain; ?>/edit?api-key=<?php echo $api_key; ?>" frameborder="0"></iframe>
    </div>

    <div id="blackwall-tab-setup" class="blackwall-tab-content">
        <?php include('dns_' . ($dns_check['status'] ? 'configured' : 'not_configured') . '.php'); ?>
    </div>
</div>

<style>
/* Completely isolated tab system with very specific selectors */
#blackwall-tab-system {
    font-family: Arial, sans-serif;
    margin-bottom: 20px;
}

#blackwall-tab-system .blackwall-tab-buttons {
    overflow: hidden;
    border: 1px solid #ccc;
    background-color: #f1f1f1;
    display: flex;
}

#blackwall-tab-system .blackwall-tab-button {
    background-color: inherit;
    float: left;
    border: none;
    outline: none;
    cursor: pointer;
    padding: 14px 16px;
    transition: 0.3s;
    font-size: 14px;
    flex: 1;
}

#blackwall-tab-system .blackwall-tab-button i {
    margin-right: 5px;
}

#blackwall-tab-system .blackwall-tab-button:hover {
    background-color: #ddd;
}

#blackwall-tab-system .blackwall-tab-button.active {
    background-color: #fff;
    border-bottom: 2px solid #4CAF50;
    color: #4CAF50;
}

#blackwall-tab-system .blackwall-tab-content {
    display: none;
    padding: 0;
    border: 1px solid #ccc;
    border-top: none;
}

#blackwall-tab-system .blackwall-tab-content.active {
    display: block;
}

#blackwall-tab-system .blackwall-iframe-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    background-color: #f8f8f8;
    border-bottom: 1px solid #ddd;
}

#blackwall-tab-system .blackwall-iframe-header span {
    font-weight: bold;
}

#blackwall-tab-system .refresh-btn {
    padding: 5px 10px;
    background-color: #f1f1f1;
    border: 1px solid #ddd;
    border-radius: 3px;
    cursor: pointer;
    transition: 0.3s;
}

#blackwall-tab-system .refresh-btn:hover {
    background-color: #e3e3e3;
}

#blackwall-tab-system .blackwall-tab-content iframe {
    width: 100%;
    height: 2100px; /* Increased height */
    border: none;
}

#blackwall-tab-system .padding20 {
    padding: 20px;
}

code {
    background: #f5f5f5;
    padding: 2px 5px;
    border-radius: 3px;
    border: 1px solid #ddd;
    font-family: monospace;
}

.copy-btn {
    display: inline-block;
    padding: 3px 8px;
    background: #f8f8f8;
    border: 1px solid #ddd;
    border-radius: 3px;
    cursor: pointer;
    color: #333;
    text-decoration: none;
}

.copy-btn:hover {
    background: #ebebeb;
}

.copy-notification {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    z-index: 1000;
    display: none;
}
</style>

<!-- Copy notification element -->
<div id="copy-notification" class="copy-notification">Copied to clipboard!</div>

<script>
// Completely isolated tab switching function
function openBlackwallTab(tabName) {
    // Hide all tab content
    var tabContents = document.getElementsByClassName("blackwall-tab-content");
    for (var i = 0; i < tabContents.length; i++) {
        tabContents[i].className = tabContents[i].className.replace(" active", "");
    }
    
    // Remove active class from all tab buttons
    var tabButtons = document.getElementsByClassName("blackwall-tab-button");
    for (var i = 0; i < tabButtons.length; i++) {
        tabButtons[i].className = tabButtons[i].className.replace(" active", "");
    }
    
    // Show the current tab, and add an "active" class to the button that opened the tab
    document.getElementById("blackwall-tab-" + tabName).className += " active";
    
    // Find and activate the button
    for (var i = 0; i < tabButtons.length; i++) {
        if (tabButtons[i].textContent.toLowerCase().indexOf(tabName) !== -1) {
            tabButtons[i].className += " active";
            break;
        }
    }
}

// Refresh iframe function
function refreshIframe(iframeId) {
    var iframe = document.getElementById(iframeId);
    iframe.src = iframe.src;
}

// Copy to clipboard function
function copyToClipboard(elementId) {
    var element = document.getElementById(elementId);
    var text = element.innerText;
    
    var tempInput = document.createElement("input");
    tempInput.value = text;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand("copy");
    document.body.removeChild(tempInput);
    
    // Show notification
    var notification = document.getElementById("copy-notification");
    notification.style.display = "block";
    
    // Highlight the copied element
    element.style.backgroundColor = "#e6ffe6";
    
    // Hide notification after 2 seconds
    setTimeout(function() {
        notification.style.display = "none";
        
        // Reset element background
        setTimeout(function() {
            element.style.backgroundColor = "";
        }, 500);
    }, 2000);
}

// Execute when page loads
document.addEventListener("DOMContentLoaded", function() {
    // First tab is active by default - no need to do anything
    console.log("Blackwall tabs initialized");
});
</script>
