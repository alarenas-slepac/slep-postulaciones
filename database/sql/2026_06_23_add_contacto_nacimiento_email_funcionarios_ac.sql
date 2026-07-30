ALTER TABLE funcionarios_ac_autorizados ADD COLUMN telefono VARCHAR(50) NULL AFTER cargo_funcion;
ALTER TABLE funcionarios_ac_autorizados ADD COLUMN fecha_nacimiento DATE NULL AFTER telefono;
ALTER TABLE funcionarios_ac_autorizados ADD COLUMN email VARCHAR(190) NULL AFTER fecha_nacimiento;
