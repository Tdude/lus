<?php
/**
 * Display login success message
 */
?>
<div id="login-overlay" class="ra-overlay">
    <div id="login-message" class="ra-login-message">
        Du måste vara inloggad för detta.
    </div>
</div>

<script>
setTimeout(function() {
    var overlay = document.getElementById('login-overlay');
    overlay.style.opacity = '0';
    setTimeout(function() {
        overlay.remove();
    }, 500);
}, 2000);
</script>