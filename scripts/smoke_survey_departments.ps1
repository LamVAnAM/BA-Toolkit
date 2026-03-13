param(
    [string]$BaseUrl = 'http://127.0.0.1:8765',
    [string]$Username = 'smoke_user',
    [string]$Password = 'Smoke@123456'
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
    if (-not $loginJson.success) {
        $results | Format-Table -AutoSize | Out-String -Width 240
        exit 0
    }
    if ($loginJson.csrf_token) {
        $csrfToken = [string]$loginJson.csrf_token
    } else {
        $csrfToken = Get-CsrfToken $BaseUrl $session
    }

    $deptName = "SMOKE_DEPT_" + [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
    $createBody = @{ name = $deptName; sponsor = 'Smoke Sponsor' } | ConvertTo-Json
    $createRes = Invoke-WebRequest -UseBasicParsing -Uri "$BaseUrl/api/departments.php" -Method POST -Body $createBody -ContentType 'application/json' -Headers @{ 'X-CSRF-Token' = $csrfToken } -WebSession $session
    $createJson = $createRes.Content | ConvertFrom-Json
    $deptId = [int]$createJson.id
    Add-Result 'departments.create' ($createJson.status -eq 'success' -and $deptId -gt 0) ("dept_id=$deptId")

    $listRes = Invoke-WebRequest -UseBasicParsing -Uri "$BaseUrl/api/departments.php" -Method GET -WebSession $session
    $listJson = $listRes.Content | ConvertFrom-Json
    $foundDept = $listJson | Where-Object { $_.id -eq $deptId }
    Add-Result 'departments.list' ($null -ne $foundDept) ("found=" + [bool]($null -ne $foundDept))

    $updateBody = @{ id = $deptId; name = "$deptName`_UPDATED"; sponsor = 'Smoke Sponsor 2' } | ConvertTo-Json
    $updateRes = Invoke-WebRequest -UseBasicParsing -Uri "$BaseUrl/api/departments.php" -Method PUT -Body $updateBody -ContentType 'application/json' -Headers @{ 'X-CSRF-Token' = $csrfToken } -WebSession $session
    $updateJson = $updateRes.Content | ConvertFrom-Json
    Add-Result 'departments.update' ($updateJson.status -eq 'success') ($updateRes.Content)

    $surveyPayload = @{
        department_id = $deptId
        sections = @(
            @{
                id = '1'
                name = '1. Organization Context'
                fields = @(
                    @{
                        key = 'dept_role'
                        label = 'Core Department Role'
                        value = 'Handle planning and reporting'
                        raw_value = 'Handle planning and reporting'
                        normalized_value = $null
                        normalization_state = 'raw'
                    },
                    @{
                        key = 'business_goals'
                        label = 'Primary Business Goals'
                        value = 'Increase process efficiency'
                        raw_value = 'Increase process efficiency'
                        normalized_value = $null
                        normalization_state = 'raw'
                    }
                )
            }
        )
        modules = @('Project Management', 'AI Assistant / Copilot', 'Custom Smoke Module')
        kpis = @('Lead time', 'Error rate')
    } | ConvertTo-Json -Depth 8

    $saveRes = Invoke-WebRequest -UseBasicParsing -Uri "$BaseUrl/api/save_survey.php" -Method POST -Body $surveyPayload -ContentType 'application/json' -Headers @{ 'X-CSRF-Token' = $csrfToken } -WebSession $session
    $saveJson = $saveRes.Content | ConvertFrom-Json
    Add-Result 'survey.save' ($saveJson.status -eq 'success') ($saveRes.Content)

    $loadRes = Invoke-WebRequest -UseBasicParsing -Uri "$BaseUrl/api/load_survey.php?department_id=$deptId" -Method GET -WebSession $session
    $loadJson = $loadRes.Content | ConvertFrom-Json

    $fieldRole = $loadJson.fields | Where-Object { $_.field_key -eq 'dept_role' } | Select-Object -First 1
    $hasRole = ($null -ne $fieldRole -and [string]$fieldRole.field_value -eq 'Handle planning and reporting')
    Add-Result 'survey.load.fields' $hasRole ("dept_role=" + [string]($fieldRole.field_value))

    $hasCustomModule = ($loadJson.modules -contains 'Custom Smoke Module')
    Add-Result 'survey.load.modules' $hasCustomModule ("modules_count=" + (($loadJson.modules | Measure-Object).Count))

    $hasKpi = ($loadJson.kpis -contains 'Lead time')
    Add-Result 'survey.load.kpis' $hasKpi ("kpis_count=" + (($loadJson.kpis | Measure-Object).Count))

    $delRes = Invoke-WebRequest -UseBasicParsing -Uri "$BaseUrl/api/departments.php?id=$deptId" -Method DELETE -Headers @{ 'X-CSRF-Token' = $csrfToken } -WebSession $session
    $delJson = $delRes.Content | ConvertFrom-Json
    Add-Result 'departments.delete' ($delJson.status -eq 'success') ($delRes.Content)
}
catch {
    Add-Result 'smoke.harness' $false $_.Exception.Message
}

$results | Format-Table -AutoSize | Out-String -Width 240
