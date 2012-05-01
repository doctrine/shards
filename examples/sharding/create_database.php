<?php
// create_database.php
require_once 'bootstrap.php';

$conn->getSchemaManager()->createDatabase('SalesDB');
