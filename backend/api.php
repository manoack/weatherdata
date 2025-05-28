<?php
// api.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'db_config.php';
require_once 'project.php';
require_once 'sensor.php';
require_once 'data.php';

$database = get_db_connection();

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input")); // Für POST/PUT-Anfragen

// Die URI analysieren
// Entfernt den RewriteBase-Pfad, um nur den "sauberen" API-Teil zu erhalten
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/backend/api/'; // HIER ANPASSEN: Muss dem Pfad in RewriteBase + /api/ entsprechen!
$request_uri_stripped = str_replace($base_path, '', $request_uri);

// Zerlegen der URI in Segmente
$uri_segments = explode('/', trim($request_uri_stripped, '/'));

$resource = $uri_segments[0] ?? ''; // projects, sensors, data
$id = $uri_segments[1] ?? null;    // ID des Eintrags
$sub_resource = $uri_segments[1] ?? null; // Für spezielle Sub-Ressourcen wie 'project' bei sensors
$sub_id = $uri_segments[2] ?? null; // ID für Sub-Ressourcen

// Bestimmen, welcher Ressourcentyp angefordert wird und welche IDs/Sub-Ressourcen vorhanden sind
switch ($resource) {
    case 'projects':
        $project = new Project($database);
        // Falls eine ID im Pfad ist (z.B. /api/projects/123)
        if (is_numeric($id)) {
            $_GET['id'] = $id; // Für die Kompatibilität mit der bestehenden Logik
        }
        handle_projects_request($method, $data, $project);
        break;

    case 'sensors':
        $sensor = new Sensor($database);
        if (is_numeric($id)) {
            $_GET['id'] = $id;
        } elseif ($sub_resource === 'project' && is_numeric($sub_id)) {
            $_GET['id_project'] = $sub_id; // Für /api/sensors/project/123
        }
        handle_sensors_request($method, $data, $sensor);
        break;

    case 'data':
        $data_obj = new Data($database);
        // Data hat keine GET-Methoden für ID im Pfad, nur POST
        handle_data_request($method, $data, $data_obj);
        break;

    default:
        http_response_code(404);
        echo json_encode(array("message" => "Resource not found."));
        break;
}

function handle_projects_request($method, $data, $project): void {
    switch ($method) {
        case 'POST': // Neues Projekt anlegen
            if (!empty($data->name)) {
                $project->name = $data->name;
                $project->description = $data->description ?? null;
                $project->passphrase = $data->passphrase ?? null;

                if ($project->create()) {
                    http_response_code(201);
                    echo json_encode(array("message" => "Project was created.", "id" => $project->id));
                } else {
                    http_response_code(503);
                    echo json_encode(array("message" => "Unable to create project."));
                }
            } else {
                http_response_code(400);
                echo json_encode(array("message" => "Unable to create project. Name is incomplete."));
            }
            break;

        case 'GET': // Projekt(e) abfragen
            if (isset($_GET['id'])) { // Hier wird $_GET['id'] verwendet, das wir oben gesetzt haben
                $project->id = $_GET['id'];
                if ($project->read_one()) {
                    $project_arr = array(
                        "id" => $project->id,
                        "name" => $project->name,
                        "description" => $project->description,
                    );
                    http_response_code(200);
                    echo json_encode($project_arr);
                } else {
                    http_response_code(404);
                    echo json_encode(array("message" => "Project not found."));
                }
            } else {
                $stmt = $project->read_all();
                $num = $stmt->rowCount();
                if ($num > 0) {
                    $projects_arr = array();
                    $projects_arr["records"] = array();
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        extract($row);
                        $project_item = array(
                            "id" => $Id,
                            "name" => $Name,
                            "description" => $Description
                        );
                        array_push($projects_arr["records"], $project_item);
                    }
                    http_response_code(200);
                    echo json_encode($projects_arr);
                } else {
                    http_response_code(404);
                    echo json_encode(array("message" => "No projects found."));
                }
            }
            break;

        case 'PUT': // Projekt aktualisieren
            // Hier muss die ID aus dem Pfad kommen, nicht aus dem Body
            if (isset($_GET['id']) && !empty($data->name)) { // Verwenden Sie $_GET['id']
                $project->id = $_GET['id'];
                $project->name = $data->name;
                $project->description = $data->description ?? null;
                $project->passphrase = $data->passphrase ?? null;

                if ($project->update()) {
                    http_response_code(200);
                    echo json_encode(array("message" => "Project was updated."));
                } else {
                    http_response_code(503);
                    echo json_encode(array("message" => "Unable to update project."));
                }
            } else {
                http_response_code(400);
                echo json_encode(array("message" => "Unable to update project. ID or Name is incomplete."));
            }
            break;

        case 'DELETE': // Projekt löschen
            if (isset($_GET['id'])) { // Hier wird $_GET['id'] verwendet
                $project->id = $_GET['id'];
                if ($project->delete()) {
                    http_response_code(200);
                    echo json_encode(array("message" => "Project was deleted."));
                } else {
                    http_response_code(503);
                    echo json_encode(array("message" => "Unable to delete project."));
                }
            } else {
                http_response_code(400);
                echo json_encode(array("message" => "Unable to delete project. Missing ID."));
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(array("message" => "Method not allowed for projects."));
            break;
    }

}

function handle_sensors_request($method, $data, $sensor) {
    switch ($method) {
        case 'POST': // Neuen Sensor anlegen
            if (!empty($data->id_project) && !empty($data->name)) {
                $sensor->id_project = $data->id_project;
                $sensor->name = $data->name;
                $sensor->active = isset($data->active) ? $data->active : true; // Standard ist true

                if ($sensor->create()) {
                    http_response_code(201);
                    echo json_encode(array("message" => "Sensor was created.", "id" => $sensor->id));
                } else {
                    http_response_code(503);
                    echo json_encode(array("message" => "Unable to create sensor."));
                }
            } else {
                http_response_code(400);
                echo json_encode(array("message" => "Unable to create sensor. id_project and name are required."));
            }
            break;

        case 'GET': // Sensor(en) abfragen
            if (isset($_GET['id'])) {
                // Einzelnen Sensor abfragen
                $sensor->id = $_GET['id'];
                if ($sensor->read_one()) {
                    $sensor_arr = array(
                        "id" => $sensor->id,
                        "id_project" => $sensor->id_project,
                        "name" => $sensor->name,
                        "active" => $sensor->active
                    );
                    http_response_code(200);
                    echo json_encode($sensor_arr);
                } else {
                    http_response_code(404);
                    echo json_encode(array("message" => "Sensor not found."));
                }
            } elseif (isset($_GET['id_project'])) {
                // Sensoren für ein spezifisches Projekt abfragen
                $stmt = $sensor->read_by_project($_GET['id_project']);
                $num = $stmt->rowCount();
                if ($num > 0) {
                    $sensors_arr["records"] = array();
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        extract($row);
                        $sensor_item = array(
                            "id" => $Id,
                            "id_project" => $IdProject,
                            "name" => $Name,
                            "active" => (bool)$Active
                        );
                        array_push($sensors_arr["records"], $sensor_item);
                    }
                    http_response_code(200);
                    echo json_encode($sensors_arr);
                } else {
                    http_response_code(404);
                    echo json_encode(array("message" => "No sensors found."));
                }
            } else {
                // Alle Sensoren abfragen
                $stmt = $sensor->read_all();
                $num = $stmt->rowCount();
                if ($num > 0) {
                    $sensors_arr["records"] = array();
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        extract($row);
                        $sensor_item = array(
                            "id" => $Id,
                            "id_project" => $IdProject,
                            "name" => $Name,
                            "active" => (bool)$Active
                        );
                        array_push($sensors_arr["records"], $sensor_item);
                    }
                    http_response_code(200);
                    echo json_encode($sensors_arr);
                } else {
                    http_response_code(404);
                    echo json_encode(array("message" => "No sensors found."));
                }
            }
            break;

        case 'PUT': // Sensor aktualisieren
            if (!empty($data->id) && !empty($data->id_project) && !empty($data->name)) {
                $sensor->id = $data->id;
                $sensor->id_project = $data->id_project;
                $sensor->name = $data->name;
                $sensor->active = $data->active ?? true;

                if ($sensor->update()) {
                    http_response_code(200);
                    echo json_encode(array("message" => "Sensor was updated."));
                } else {
                    http_response_code(503);
                    echo json_encode(array("message" => "Unable to update sensor."));
                }
            } else {
                http_response_code(400);
                echo json_encode(array("message" => "Unable to update sensor. Data is incomplete."));
            }
            break;

        case 'DELETE': // Sensor löschen
            if (isset($_GET['id'])) {
                $sensor->id = $_GET['id'];
                if ($sensor->delete()) {
                    http_response_code(200);
                    echo json_encode(array("message" => "Sensor was deleted."));
                } else {
                    http_response_code(503);
                    echo json_encode(array("message" => "Unable to delete sensor."));
                }
            } else {
                http_response_code(400);
                echo json_encode(array("message" => "Unable to delete sensor. Missing ID."));
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(array("message" => "Method not allowed for sensors."));
            break;
    }
}

function handle_data_request($method, $data, $data_obj) {
    switch ($method) {
        case 'POST': // Neue Daten anlegen
            if (!empty($data->id_sensor) && isset($data->value_date) && isset($data->value)) {
                $data_obj->id_sensor = $data->id_sensor;
                $data_obj->value_date = $data->value_date;
                $data_obj->value = $data->value;

                // Zusätzliche Validierung für value_date (BIGINT) und value (FLOAT)
                if (!is_numeric($data_obj->value_date) || !is_numeric($data_obj->value)) {
                    http_response_code(400);
                    echo json_encode(array("message" => "Invalid data types for value_date or value."));
                    return;
                }

                if ($data_obj->create()) {
                    http_response_code(201);
                    echo json_encode(array("message" => "Data was created.", "id" => $data_obj->id));
                } else {
                    http_response_code(503);
                    echo json_encode(array("message" => "Unable to create data entry."));
                }
            } else {
                http_response_code(400);
                echo json_encode(array("message" => "Unable to create data entry. id_sensor, value_date, and value are required."));
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(array("message" => "Method not allowed for data. Only POST is allowed."));
            break;
    }
}
