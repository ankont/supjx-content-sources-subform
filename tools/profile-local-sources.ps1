param([string]$Container='catichisi-local-web-1')
$ErrorActionPreference='Stop'
if($Container -ne 'catichisi-local-web-1'){throw 'Isolated local runtime only'}
$root=Split-Path -Parent $PSScriptRoot
$artifacts=Join-Path $root 'tests/artifacts'
New-Item -ItemType Directory -Force -Path $artifacts | Out-Null
function Invoke-Docker {
    $output=& docker.exe @args
    if($LASTEXITCODE -ne 0){throw ('Docker failed: '+($args -join ' '))}
    return $output
}
Invoke-Docker exec $Container test -f /opt/local/localize.php
Invoke-Docker exec $Container test ! -f /tmp/catichisi-method-timing/manifest.json
$backup='/tmp/sources-performance-'+[guid]::NewGuid().ToString('N')
Invoke-Docker exec $Container mkdir -m 700 $backup
$relative=@('content/sources/src/Support/InlineRenderer.php','content/sources/src/Support/LegacySources.php',
    'content/sources/src/Support/RequestCache.php','content/sources/src/Support/SourceFieldData.php','fields/sources/tmpl/sources.php')
$files=@()
foreach($name in $relative){
    $target='/var/www/html/plugins/'+$name
    & docker.exe exec $Container test -f $target
    $exists=$LASTEXITCODE -eq 0
    $saved=$backup+'/'+$files.Count
    $original=$null
    if($exists){
        $original=((Invoke-Docker exec $Container sha256sum $target) -split ' ')[0]
        Invoke-Docker exec $Container cp -p $target $saved
    }
    $files+=[pscustomobject]@{Target=$target;Source=(Join-Path $root ('package/plugins/'+$name));Saved=$saved;Exists=$exists;Original=$original;Candidate=$null}
}
function Measure-Sources([string]$phase){
    Invoke-Docker exec --user www-data $Container php /tmp/catichisi-local-timing.php install
    try{
        Start-Sleep -Seconds 3
        $html=Join-Path $artifacts ('sources-'+$phase+'.html')
        $timing=& curl.exe -sS --max-time 150 -o $html -w 'status=%{http_code} ttfb=%{time_starttransfer} total=%{time_total}' -H 'X-Catichisi-Timing: local-profile' 'http://localhost:8089/selections/weekly/2o-mathima-mesis-anoteris-vathmidas.html'
        if($LASTEXITCODE -ne 0 -or $timing -notmatch 'status=200 '){throw ('Frontend request failed: '+$timing)}
        $reports=Invoke-Docker exec $Container find /tmp/catichisi-method-timing -name 'request-*.json' -printf '%T@ %p\n'
        $last=$reports | Sort-Object {[double](($_ -split ' ')[0])} -Descending | Select-Object -First 1
        $path=($last -split ' ',2)[1]
        $json=(Invoke-Docker exec --user www-data $Container cat $path) -join [Environment]::NewLine
        [IO.File]::WriteAllText((Join-Path $artifacts ('sources-'+$phase+'.json')),$json)
        Write-Output $phase $timing
        ($json | ConvertFrom-Json).rows | Where-Object name -in @('FieldsHelper::getFields','SelectionFieldSupport::prepare','InlineRenderer::render','InlineRenderer::prepareOutput','InlineRenderer::resourceItem') |
            Select-Object name,calls,total_ms,self_ms | ConvertTo-Json
    }finally{
        Invoke-Docker exec --user www-data $Container php /tmp/catichisi-local-timing.php restore
    }
}
try{
    Measure-Sources 'before'
    foreach($file in $files){
        Invoke-Docker cp $file.Source ($Container+':'+$file.Target)
        Invoke-Docker exec $Container chown www-data:www-data $file.Target
        $file.Candidate=((Invoke-Docker exec $Container sha256sum $file.Target) -split ' ')[0]
        Invoke-Docker exec $Container php -l $file.Target
    }
    Measure-Sources 'after'
}finally{
    # Check every candidate before restoring anything; never overwrite a concurrent edit.
    foreach($file in $files){
        if($file.Candidate){
            $current=((Invoke-Docker exec $Container sha256sum $file.Target) -split ' ')[0]
            if($current -ne $file.Candidate){throw ('Runtime changed; originals retained at '+$backup)}
        }
    }
    foreach($file in $files){
        if(-not $file.Candidate){continue}
        if($file.Exists){
            Invoke-Docker exec $Container cp -p $file.Saved $file.Target
            $restored=((Invoke-Docker exec $Container sha256sum $file.Target) -split ' ')[0]
            if($restored -ne $file.Original){throw 'Restoration hash mismatch'}
        }else{Invoke-Docker exec $Container rm -- $file.Target}
    }
    Write-Output ('Original Sources files restored; backup: '+$backup)
}
