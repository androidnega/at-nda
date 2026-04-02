# Get the LAN IP - the adapter that has a default gateway (connected to router/WiFi)
$config = Get-NetIPConfiguration -ErrorAction SilentlyContinue | 
    Where-Object { $_.IPv4DefaultGateway -ne $null -and $_.IPv4Address.IPAddress -notmatch '^169\.' } | 
    Select-Object -First 1
if ($config) {
    $config.IPv4Address.IPAddress
} else {
    # Fallback: first non-loopback, non-link-local IPv4
    (Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue | 
        Where-Object { $_.IPAddress -notmatch '^127\.|^169\.' } | 
        Select-Object -First 1).IPAddress
}
