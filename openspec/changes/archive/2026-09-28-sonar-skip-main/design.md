## Context

El job `sonar` corre en cada push a `main`/`develop` y en cada PR, siempre contra el proyecto `MyDiary`. El `concurrency` del workflow (`ci-${{ github.ref }}`) es por rama, así que no evita que los CI de `main` y `develop` lancen el análisis a la vez. En la release 0.16.0 los dos informes llegaron con 0,2 s de diferencia y el de `main` falló en el servidor.

## Goals / Non-Goals

**Goals:** que una release no deje el CI de `main` en rojo por culpa de Sonar.

**Non-Goals:** análisis por rama (requiere SonarQube Developer Edition o proyectos separados).

## Decisions

- **`if: github.ref != 'refs/heads/main'` en el job `sonar`.** Elegida por el usuario frente a:
  - *Grupo de `concurrency` para serializar los análisis:* evita el choque, pero analiza dos veces el mismo código.
  - *Un proyecto de Sonar por rama (`MyDiary-main`):* otro proyecto y otro token para poca ganancia.
- Los hotfixes también hacen push a `main`, pero se mergean a la vez en `develop`, cuyo CI sí los analiza.

## Risks / Trade-offs

- [Código que llegue a `main` sin pasar por `develop`] → Git Flow lo impide: releases y hotfixes siempre se mergean también a `develop`.
