# Portal N.E.P. 30 - Baja a tu PC las copias semanales de la VM.
# Uso (PowerShell, con la VM prendida):  powershell -ExecutionPolicy Bypass -File D:\tif\vps_tfi\bajar_backups.ps1
# Las copias se guardan en C: (D: es FAT32 y no protege los archivos).

$Clave   = "$HOME\.ssh\vm-tfi-nep30_key.pem"
$Servidor = "azureuser@portal-nep30-tfi.northcentralus.cloudapp.azure.com"
$Destino = "$HOME\Documents\backups_tfi"

New-Item -ItemType Directory -Force $Destino | Out-Null
Write-Host "Bajando copias semanales a $Destino ..."
scp -i $Clave "${Servidor}:~/backups/semanal/*.gz" $Destino
if ($LASTEXITCODE -eq 0) {
    Write-Host "Listo. Copias en tu PC:"
    Get-ChildItem $Destino -Filter *.gz | Sort-Object LastWriteTime -Descending |
        Select-Object Name, @{n='Tamaño';e={'{0:N0} KB' -f ($_.Length/1KB)}}, LastWriteTime | Format-Table -AutoSize
} else {
    Write-Host "No se pudo bajar. ¿La VM está prendida?" -ForegroundColor Red
}
