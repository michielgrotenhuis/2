<div class="padding20">
    <h3>DNS Configuration Instructions</h3>
    
    <div class="green-info" style="background: #0c840c; color: white; border: 1px solid #096d09; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <p><strong>✓ Your domain is correctly configured!</strong></p>
        <p>Your domain <strong><?php echo $domain; ?></strong> is properly connected to Blackwall protection via node <strong><?php echo $dns_check['connected_to']; ?></strong>.</p>
        
        <?php if(!empty($dns_check['missing_records'])): ?>
            <p style="margin-top: 10px;"><strong>For comprehensive protection, consider adding these missing records:</strong></p>
            <ul>
                <?php foreach($dns_check['missing_records'] as $record): ?>
                    <li>Add <?php echo $record['type']; ?> record for your domain pointing to <code style="background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.3);"><?php echo $record['value']; ?></code></li>
                <?php endforeach; ?>
            </ul>
            <p>This ensures both IPv4 and IPv6 protection for maximum security.</p>
        <?php endif; ?>
    </div>
    
    <h4>What's Next?</h4>
    <p>Your website is now protected by Blackwall BotGuard. Here are some things you can do:</p>
    
    <ol>
        <li><strong>Check Statistics:</strong> Click on the "View Statistics" tab to see how Blackwall is protecting your website.</li>
        <li><strong>View Event Logs:</strong> Use the "View Events Log" tab to see details about blocked threats and visitors.</li>
        <li><strong>Customize Protection:</strong> Go to the "Edit Protection Settings" tab to customize your protection rules.</li>
    </ol>
    
    <h4>Need Help?</h4>
    <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
</div>
