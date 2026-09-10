#!/bin/bash

echo "=== Security Sandbox Test ==="
echo ""

echo "1. Current user:"
whoami
echo ""

echo "2. Current directory:"
pwd
echo ""

echo "3. Files in /tmp:"
ls -la /tmp 2>&1 | head -10
echo ""

echo "4. Files in /home/current:"
if [ -d "/home/$(whoami)" ]; then
  ls -la /home/$(whoami)/ | head -20
else
  echo "/home/$(whoami) не существует"
fi
echo ""

echo "5. Access to /root:"
ls -la /root 2>&1
echo ""

echo "6. Access to /opt:"
ls -la /opt 2>&1
echo ""

echo "7. Access to /etc/passwd:"
ls -la /etc/passwd
echo ""

echo "8. Access to /var/www:"
ls -la /var/www/ 2>&1 | head -15
echo ""

echo "9. Environment variables (PATH restricted):"
echo $PATH
echo ""

echo "10. Test restricted shell access:"
echo "Working directory: $(pwd)"
echo ""

echo "=== Test completed ==="