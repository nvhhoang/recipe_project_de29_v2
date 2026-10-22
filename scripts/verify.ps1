$ErrorActionPreference = 'Continue'
$urls = @(
    @{ Name = 'Website'; Url = 'http://localhost:8080/' },
    @{ Name = 'Admin login'; Url = 'http://localhost:8080/admin.php' },
    @{ Name = 'Nginx health'; Url = 'http://localhost:8080/health' },
    @{ Name = 'phpMyAdmin'; Url = 'http://localhost:8081/' },
    @{ Name = 'Prometheus'; Url = 'http://localhost:9090/-/ready' },
    @{ Name = 'Prometheus targets API'; Url = 'http://localhost:9090/api/v1/targets' },
    @{ Name = 'Grafana'; Url = 'http://localhost:3000/api/health' }
)

Write-Host '=== Docker Compose status ===' -ForegroundColor Cyan
docker compose ps
Write-Host ''
Write-Host '=== HTTP smoke tests ===' -ForegroundColor Cyan
foreach ($item in $urls) {
    try {
        $response = Invoke-WebRequest -Uri $item.Url -UseBasicParsing -TimeoutSec 8
        Write-Host ('[OK]   {0,-24} HTTP {1}' -f $item.Name, [int]$response.StatusCode) -ForegroundColor Green
    } catch {
        $status = 'ERROR'
        if ($_.Exception.Response -and $_.Exception.Response.StatusCode) {
            $status = 'HTTP ' + [int]$_.Exception.Response.StatusCode
        }
        Write-Host ('[FAIL] {0,-24} {1}' -f $item.Name, $status) -ForegroundColor Yellow
    }
}
Write-Host ''
Write-Host 'If a service fails, inspect: docker compose logs --tail=100' -ForegroundColor Cyan
