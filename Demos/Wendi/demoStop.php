<?php

error_reporting (E_ALL);

define("RUN_KEY", 1);

$shmkey = "123456";

$resource = shm_attach($shmkey);

shm_remove_var($resource, RUN_KEY);

shm_detach($resource);

?>
