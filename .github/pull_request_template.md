## Qué cambia
<!-- Una o dos líneas. -->

Closes #<!-- número de la issue: al aprobar el PR la issue se cierra sola -->

## Cómo probarlo
1. 
2. 

## Checklist
- [ ] La rama sale de `develop` y va hacia `develop` (o de `main` si es `hotfix/`)
- [ ] Commits con el formato `tipo(área): descripción (#issue)`
- [ ] `php artisan test` pasa
- [ ] Si cambia la base de datos: hay migración nueva (no se edita una ya aplicada)
- [ ] Sin claves ni contraseñas en el código (van en `.env`, que no se sube)
- [ ] Probado en el celular si toca pantallas
