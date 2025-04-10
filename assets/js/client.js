/**
 * Blackwall Client JavaScript
 */

// Global variables
var blackwallRefreshTimers = {};

/**
 * Initialize Blackwall client interface
 */
function initBlackwallClient() {
    // Setup auto-refresh for iframes
    setupAutoRefresh('statistics-iframe', 300); // Refresh every 5 minutes
    setupAutoRefresh('events-iframe', 120);     // Refresh every 2 minutes
    
    // Check if DNS is properly configured
    checkDNSConfiguration();
}

/**
 * Setup auto-refresh for an iframe
 * 
 * @param {string} iframeId ID of the iframe to refresh
 * @param {number} seconds Seconds between refreshes
 */
function setupAutoRefresh(iframeId, seconds) {
    if (blackwallRefreshTimers[iframeId]) {
        clearInterval(blackwallRefreshTimers[iframeId]);
    }
    
    blackwallRefreshTimers[iframeId] = setInterval(function() {
        refreshIframe(iframeId);
    }, seconds * 1000);
}

/**
 * Refresh an iframe
 * 
 * @param {string} iframeId ID of the iframe to refresh
 */
function refreshIframe(iframeId) {
    var iframe = document.getElementById(iframeId);
    if (iframe) {
        iframe.src = iframe.src;
    }
}

/**
 * Check DNS configuration
 */
function checkDNSConfiguration() {
    // This would typically be an AJAX call to check DNS status
    // For now, we'll rely on the server-side check
}

/**
 * Copy text to clipboard
 * 
 * @param {string} elementId ID of the element containing text to copy
 */
function copyToClipboard(elementId) {
    var element = document.getElementById(elementId);
    if (!element) return;
    
    var text = element.innerText;
    
    var tempInput = document.createElement("input");
    tempInput.value = text;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand("copy");
    document.body.removeChild(tempInput);
    
    // Show notification
    var notification = document.getElementById("copy-notification");
    if (notification) {
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
}

/**
 * Switch tabs in the client area
 * 
 * @param {string} tabName Name of the tab to show
 */
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
    
    // Show the current tab
    var currentTab = document.getElementById("blackwall-tab-" + tabName);
    if (currentTab) {
        currentTab.className += " active";
    }
    
    // Find and activate the button
    for (var i = 0; i < tabButtons.length; i++) {
        if (tabButtons[i].textContent.toLowerCase().indexOf(tabName) !== -1) {
            tabButtons[i].className += " active";
            break;
        }
    }
}

// Initialize when the DOM is ready
document.addEventListener("DOMContentLoaded", function() {
    initBlackwallClient();
});
