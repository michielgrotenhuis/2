<div class="padding20">
    <h3>DNS Configuration Instructions</h3>
    
    <div class="red-info" style="background: #d80000; color: white; border: 1px solid #a00000; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <p><strong>⚠️ Your domain is not correctly configured for Blackwall protection</strong></p>
        <p>Please follow the steps below to connect your domain to our protection servers:</p>
    </div>
    
    <h4>DNS Records to Add:</h4>
    <p>Add the following DNS records to your domain's DNS configuration:</p>
    
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
        <thead>
            <tr>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Record Type</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Value</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Copy</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Purpose</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($dns_check['missing_records'] as $index => $record): ?>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px;"><?php echo $record['type']; ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px; font-family: monospace;" id="ip-value-<?php echo $record['type']; ?>"><?php echo $record['value']; ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">
                        <a href="javascript:void(0);" onclick="copyToClipboard('ip-value-<?php echo $record['type']; ?>')" class="copy-btn">
                            <i class="fa fa-copy"></i>
                        </a>
                    </td>
                    <td style="border: 1px solid #ddd; padding: 8px;">
                        <?php echo $record['type'] == 'A' ? 'Connect to Blackwall Protection (IPv4)' : 'Connect to Blackwall Protection (IPv6)'; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <h4>Important Notes:</h4>
    <ul>
        <li>You can choose to connect to either bg-gk-01 or bg-gk-02 node, but the same node should be used for both A and AAAA records.</li>
        <li>DNS changes can take up to 24 hours to propagate worldwide.</li>
        <li>After updating your DNS records, you can return to this page to check if the configuration is successful.</li>
        <li><strong>Subdomains Protection:</strong> If you want subdomains to be protected (e.g., blog.yourdomain.com), they should also point to the same Blackwall node as your root domain.</li>
    </ul>
    
    <h4>Alternative Nodes:</h4>
    <p>You can use any of the following nodes to connect to Blackwall protection:</p>
    
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Node</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">IPv4 Record (A)</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Copy</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">IPv6 Record (AAAA)</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Copy</th>
            </tr>
        </thead>
        <tbody>
            <?php $counter = 0; foreach($gatekeeper_nodes as $node_name => $ips): $counter++; ?>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px;"><?php echo $node_name; ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px; font-family: monospace;" id="ipv4-<?php echo $counter; ?>"><?php echo $ips['ipv4']; ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">
                        <a href="javascript:void(0);" onclick="copyToClipboard('ipv4-<?php echo $counter; ?>')" class="copy-btn">
                            <i class="fa fa-copy"></i>
                        </a>
                    </td>
                    <td style="border: 1px solid #ddd; padding: 8px; font-family: monospace;" id="ipv6-<?php echo $counter; ?>"><?php echo $ips['ipv6']; ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">
                        <a href="javascript:void(0);" onclick="copyToClipboard('ipv6-<?php echo $counter; ?>')" class="copy-btn">
                            <i class="fa fa-copy"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <h4>How to Update Your DNS Records</h4>
    <p>The process to update DNS records varies depending on your domain registrar or DNS provider. Here are general steps:</p>
    
    <ol>
        <li>Log in to your domain registrar or DNS provider's control panel</li>
        <li>Find the DNS management section</li>
        <li>Locate your domain's DNS records</li>
        <li>Add or modify the A and AAAA records with the values provided above</li>
        <li>Save your changes</li>
    </ol>
    
    <p>If you need assistance, please contact our support team for help with configuring your DNS records.</p>
</div>
