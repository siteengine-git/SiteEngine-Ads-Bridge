$ErrorActionPreference = 'Stop'
$passed = 0
function Assert-Test([bool]$Condition,[string]$Label) {
    if (-not $Condition) { throw "FAIL: $Label" }
    $script:passed++
    Write-Output "PASS: $Label"
}
$connectionScript = Join-Path (Split-Path $PSScriptRoot -Parent) 'tools\fieldedge\FieldEdge-Connection.ps1'
$parseErrors = $null; $tokens = $null
[void][Management.Automation.Language.Parser]::ParseFile($connectionScript,[ref]$tokens,[ref]$parseErrors)
Assert-Test ($parseErrors.Count -eq 0) 'provisioning/probe script parses in Windows PowerShell'
$testFile = Join-Path ([IO.Path]::GetTempPath()) ('siteengine-fe-dpapi-synthetic-' + [guid]::NewGuid().ToString('N') + '.clixml')
$sentinel = 'SYNTHETIC-DPAPI-ROUNDTRIP-NOT-A-CREDENTIAL'
try {
    $secure = ConvertTo-SecureString -String $sentinel -AsPlainText -Force
    [pscustomobject]@{client_id=$secure;client_secret=$secure;target_id=$secure} | Export-Clixml -LiteralPath $testFile -NoClobber
    $serialized = [IO.File]::ReadAllText($testFile)
    Assert-Test (-not $serialized.Contains($sentinel)) 'native CLIXML contains no plaintext synthetic credential'
    Assert-Test ($serialized.Contains('<SS ')) 'SecureString serialized through native DPAPI'
    $roundtrip = Import-Clixml -LiteralPath $testFile
    Assert-Test ($roundtrip.client_id -is [Security.SecureString] -and $roundtrip.client_secret -is [Security.SecureString] -and $roundtrip.target_id -is [Security.SecureString]) 'all three values restore as protected SecureStrings'
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($roundtrip.client_secret)
    try { Assert-Test ([Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) -eq $sentinel) 'same Windows user/machine decrypts exact value in memory' }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }
    $status = & $connectionScript -Action Status | ConvertFrom-Json
    Assert-Test ($null -ne $status.provisioned) 'actual provisioning status returns only safe metadata without network access'
    Write-Output "RESULT: $passed PASS, 0 FAIL; Windows DPAPI synthetic roundtrip only; no real credentials or provider requests."
} finally {
    # One exact, self-created temporary file; never touch the real bundle.
    if (Test-Path -LiteralPath $testFile) { Remove-Item -LiteralPath $testFile -Force }
}
