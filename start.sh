#!/bin/bash
cd "$(dirname "$0")"
IP=$(ipconfig getifaddr en0 2>/dev/null || hostname -I 2>/dev/null | awk '{print $1}')
[ -n "$IP" ] && echo "LAN: http://$IP:8000"
php artisan serve --host=0.0.0.0
