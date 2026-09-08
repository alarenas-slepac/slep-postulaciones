<?php

namespace App\Models;

use LogicException;

/** Proyección de lectura. Nunca guarda sus valores históricos sobre el padrón vigente. */
class ReemplazoPersonalHistorico extends ReemplazoPersonal
{
    public function save(array $options = [])
    {
        throw new LogicException('La copia contractual histórica es de solo lectura.');
    }

    public function delete()
    {
        throw new LogicException('La copia contractual histórica no se puede eliminar mediante el padrón.');
    }

    public function refresh()
    {
        return $this;
    }

    public function fresh($with = [])
    {
        return clone $this;
    }
}
