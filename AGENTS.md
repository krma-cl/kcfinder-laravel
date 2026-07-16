# Orientación para trabajar en el adaptador Laravel

Este repositorio integra el núcleo de KCFinder con Laravel. Lee primero `README.md` y la [guía canónica del ecosistema](https://krma-cl.github.io/kcfinder-docs/roadmap/maintainer-guide).

## Línea base

- Paquete: `krma-cl/kcfinder-laravel`
- Release estable al crear esta guía: `v1.2.1`
- Requiere PHP 8.2+, Laravel 12 o 13 y `krma-cl/kcfinder ^4.6`
- Rama principal: `main`

## Responsabilidad y límites

- Aquí pertenecen Laravel Storage, autorización, resolutores de URLs, resultados estructurados y eventos Laravel.
- No copies ni modifiques el navegador clásico dentro del paquete.
- El núcleo debe seguir sin dependencias de Laravel.
- `SelectedUrlResolverInterface` extiende el contrato base de URLs; conserva esa sustitución.
- `ClassicBrowserBridge` adapta `OperationObserverInterface` a los eventos del paquete.
- El puente automático sólo se configura si KCFinder ya se ejecuta dentro del bootstrap autenticado de Laravel. Nunca arranques Laravel otra vez desde `conf/config.local.php`.
- No combines el puente automático y llamadas manuales `report*` para la misma operación.
- Mantén optativo el protocolo estructurado para no romper respuestas históricas.

## Validación

```bash
composer install
composer check
```

La CI debe cubrir las combinaciones declaradas de PHP y Laravel. Si cambias restricciones o la dependencia del núcleo, prueba además `composer require` desde un proyecto vacío.

## Flujo y documentación

- Usa ramas `krma/<descripcion>`.
- Agrega pruebas para contratos, contenedor, autorización, URLs, snapshots y eventos afectados.
- Actualiza `README.md` y la documentación pública en `kcfinder-docs` cuando cambie la integración.
- Publica con SemVer. Verifica que el tag y la versión en Packagist correspondan al mismo commit.
