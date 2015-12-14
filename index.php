<?php require_once 'settings.php'; ?>
<form onsubmit="outlookConnect();return false;">
    <p>
        <label for="user"><acronym title="Google Voice">GV</acronym> Login:</label> <input type="text" id="user"
                                                                                           name="UserID"/><br/>
        <input type="submit" value="Setup!"/>
    </p>
</form>
<script type="text/javascript">
    function outlookConnect() {
        UserID = document.getElementById('user').value;
        document.location = 'oms:<?php echo Settings::SERVICE_BASE; ?>/service.php?UserID=' + UserID;
    }
</script>
