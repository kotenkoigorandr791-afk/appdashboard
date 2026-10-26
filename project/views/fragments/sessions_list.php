                <?php if(empty($activeSessions)):?><p><?php echo my_htmlspecialchars($translations['no_active_sessions']); ?></p>
                <?php else:?>
                    <table class="sessions-table">
                        <thead><tr><th><?php echo my_htmlspecialchars($translations['device']); ?></th><th><?php echo my_htmlspecialchars($translations['browser']); ?></th><th><?php echo my_htmlspecialchars($translations['login_time']); ?></th><th><?php echo my_htmlspecialchars($translations['last_activity']); ?></th><th><?php echo my_htmlspecialchars($translations['ip_address']); ?></th><th><?php echo my_htmlspecialchars($translations['terminate_session']); ?></th></tr></thead>
                        <tbody>
                        <?php foreach($activeSessions as $sessionId=>$session):?>
                            <tr class="<?php echo $sessionId===session_id()?'current-session':''; ?>">
                                <td><div class="device-browser-item"><?php $device=$session['device']??'unknown_device';$deviceClass=$device.'-icon';$deviceText=$translations[$device]??$translations['unknown_device'];?><div class="device-icon <?php echo $deviceClass; ?>"><?php echo get_device_icon_html($device); ?></div><span><?php echo my_htmlspecialchars($deviceText); ?></span></div></td>
                                <td><div class="device-browser-item"><?php $browser=$session['browser']??'unknown_browser';$browserClass=$browser.'-icon';$browserText=$translations[$browser]??$translations['unknown_browser'];?><div class="browser-icon <?php echo $browserClass; ?>"><?php echo get_browser_icon_svg($browser); ?></div><span><?php echo my_htmlspecialchars($browserText); ?></span></div></td>
                                <td><?php echo date('Y-m-d H:i:s',$session['login_time']); ?></td>
                                <td><?php echo date('Y-m-d H:i:s',$session['last_activity']); ?></td>
                            <td class="ip-location-cell" title="<?php echo my_htmlspecialchars($session['ip_location'] ?: ($translations['unknown_location'] ?? 'Unknown location')); ?>"><?php echo my_htmlspecialchars($session['ip_address']??'Unknown'); ?></td>
                                <td><?php if($sessionId!==session_id()):?><form method="post" style="display:inline;"><input type="hidden" name="session_id" value="<?php echo my_htmlspecialchars($sessionId); ?>"><button type="submit" name="terminate_session" class="terminate-btn"><?php echo my_htmlspecialchars($translations['terminate_session']); ?></button></form><?php else:?><span style="color:#666;"><?php echo my_htmlspecialchars($translations['current_session']); ?></span><?php endif;?></td>
                            </tr>
                        <?php endforeach;?>
                        </tbody>
                    </table>
                    <div style="margin-top:20px;"><form method="post"><button type="submit" name="terminate_all_sessions" class="auth-button" style="background:#ff4444;"><?php echo my_htmlspecialchars($translations['terminate_all_sessions']); ?></button></form></div>
                <?php endif;?>
