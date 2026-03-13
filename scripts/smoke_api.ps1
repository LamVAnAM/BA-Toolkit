param(
    [string]$BaseUrl = 'http://127.0.0.1:8765',
    [string]$Username = 'smoke_admin',
    [string]$Password = 'Smoke@123456',
    [string]$Provider = 'ollama',
    [string]$Endpoint = 'http://127.0.0.1:11434/api/chat',
    [string]$Model = 'llama3.1:8b'
)

$ErrorActionPreference = 'Stop'

$results = @()
function Add-Result($name, $ok, $detail) {
    $script:results += [pscustomobject]@{
        step = $name
        ok = $ok
        detail = $detail
    }
}

function Read-WebExceptionBody($err) {
    $resp = $err.Exception.Response
    if ($resp -and $resp.GetResponseStream()) {
        $reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
        $body = $reader.ReadToEnd()
        $reader.Close()
        return $body
    }
    return $err.Exception.Message
}

function Get-CsrfToken($baseUrl, $session) {
    $page = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/index.php" -Method GET -WebSession $session
    if ($page.Content -match 'window\.CSRF_TOKEN = "([^"]+)"') {
        return $matches[1]
    }
    throw 'CSRF token not found in index page.'
}

try {
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $csrfToken = Get-CsrfToken $BaseUrl $session

    $loginBody = @{ username = $Username; password = $Password } | ConvertTo-Json
    $loginRes = Invoke-WebRequest -UseBasicParsing -Uri "$BaseUrl/api/auth.php?action=login" -Method POST -Body $loginBody -ContentType 'application/json' -Headers @{ 'X-CSRF-Token' = $csrfToken } -WebSession $session
    $loginJson = $loginRes.Content | ConvertFrom-Json
    Add-Result 'auth.login' ([bool]$loginJson.success) ($loginJson.message)
    if ($loginJson.csrf_token) {
        $csrfToken = [string]$loginJson.csrf_token
    } else {
        $csrfToken = Get-CsrfToken $BaseUrl $session
    }

    $getSettings = Invoke-WebRequest -UseBasicParsing -Uri "$BaseUrl/api/settings.php" -Method GET -WebSession $session
    $settingsJson = $getSettings.Content | ConvertFrom-Json
    $hasProvider = $settingsJson.PSObject.Properties.Name -contains 'ai_provider'
    Add-Result 'settings.get' $hasProvider ("ai_provider=" + [string]$settingsJson.ai_provider)

    $savePayload = @{
        ai_provider = $Provider
        ai_endpoint = $Endpoint.Replace('/api/chat', '')
        ai_model = $Model
        ai_timeout_sec = '45'
        ai_ssl_verify = '0'
        ai_ssl_verify_host = '0'
    } | ConvertTo-Json
    $saveRes = Invoke-WebRequest -UseBasicParsing -Uri "$BaseUrl/api/settings.php" -Method POST -Body $savePayload -ContentType 'application/json' -Headers @{ 'X-CSRF-Token' = $csrfToken } -WebSession $session
    $saveJson = $saveRes.Content | ConvertFrom-Json
    Add-Result 'settings.save' ([bool]$saveJson.success) ($saveRes.Content)

    $verifyRes = Invoke-WebRequest -UseBasicParsing -Uri "$BaseUrl/api/settings.php" -Method GET -WebSession $session
    $verifyJson = $verifyRes.Content | ConvertFrom-Json
    $okVerify = ($verifyJson.ai_provider -eq $Provider -and [string]$verifyJson.ai_model -eq $Model)
    Add-Result 'settings.verify' $okVerify ("provider=$($verifyJson.ai_provider), endpoint=$($verifyJson.ai_endpoint), model=$($verifyJson.ai_model)")

    $testPayload = @{
        ai_provider = $Provider
        ai_endpoint = $Endpoint
        ai_model = $Model
    } | ConvertTo-Json
    try {
        $testRes = Invoke-WebRequest -UseBasicParsing -Uri "$BaseUrl/api/ai_tools.php?action=test" -Method POST -Body $testPayload -ContentType 'application/json' -Headers @{ 'X-CSRF-Token' = $csrfToken } -WebSession $session
        $testJson = $testRes.Content | ConvertFrom-Json
        Add-Result 'ai_tools.test' ([bool]$testJson.success) ($testRes.Content.Substring(0, [Math]::Min(200, $testRes.Content.Length)))
    } catch {
        $body = Read-WebExceptionBody $_
        $isJsonErr = $false
        try {
            $obj = $body | ConvertFrom-Json
            if ($obj.error) { $isJsonErr = $true }
        } catch {}
        Add-Result 'ai_tools.test' $isJsonErr ("error=" + $body.Substring(0, [Math]::Min(220, $body.Length)))
    }

    try {
        $listEndpoint = $Endpoint.Replace('/api/chat', '')
        $modelsRes = Invoke-WebRequest -UseBasicParsing -Uri "$BaseUrl/api/ai_tools.php?action=list_models&provider=$Provider&endpoint=$([uri]::EscapeDataString($listEndpoint))" -Method GET -WebSession $session
        $modelsJson = $modelsRes.Content | ConvertFrom-Json
        $okModels = ($modelsJson.success -eq $true -and $null -ne $modelsJson.models)
        $count = ($modelsJson.models | Measure-Object).Count
        Add-Result 'ai_tools.list_models' $okModels ("count=$count")
    } catch {
        $body = Read-WebExceptionBody $_
        $isJsonErr = $false
        try {
            $obj = $body | ConvertFrom-Json
            if ($obj.error) { $isJsonErr = $true }
        } catch {}
        Add-Result 'ai_tools.list_models' $isJsonErr ("error=" + $body.Substring(0, [Math]::Min(220, $body.Length)))
    }
} catch {
    Add-Result 'smoke.harness' $false $_.Exception.Message
}

$results | Format-Table -AutoSize | Out-String -Width 240
