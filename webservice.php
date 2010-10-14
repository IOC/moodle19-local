<?php

global $CFG;

require_once('../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grade/grade_grade.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->dirroot . '/grade/querylib.php');

//Comprovar si el servidor està fent servir SSL
if (!isset($_SERVER['HTTPS'])) {
    die;
}

//Contrasenya per poder fer servir el webservice
if (empty($CFG->local_webservice_password)) {
    die;
}
$clau = $CFG->local_webservice_password;

$data = stripslashes($_POST['data']);
$data = json_decode($data);

//Comprovar si la clau es correcte
if ($data->pass !== $clau) {
    die;
}

//Funcions valides
static $functions = array ('Cursid', 'EstudiantsCurs', 'NomCurs', 'NotesActivitats',
                           'NotesiCategories', 'QualificacionsCursActivitats',
                           'QualificacionsCursCategories');

//Si la funció es valida la cridem
if (in_array($data->func, $functions)){
    echo json_encode(call_user_func_array($data->func,$data->params));
}

/*
 * FUNCIONS DEL WEBSERVICE
 */

function NotesiCategories($params){
	//La funció grade_get_formatted_grades no està present en Moodle 1.9
    return list($grades_by_student, $all_categories) = grade_get_formatted_grades();
}

function Cursid($id){
    return get_record('course', 'id', $id);
}

function NomCurs($courseid){
    return get_field('course', 'fullname', 'id', $courseid);
}

function NotesActivitats($shortname, $module, $usersids){

    $activities = array();

    $courseid = get_field('course', 'id', 'shortname', $shortname);
    $activitats = grade_get_gradable_activities($courseid, $module);
    $i = 0;
    foreach ($activitats as $cm)
    {
       //$activities[$i] = array('visible' => $cm->visible, 'name' => $cm->name);
	$activities[$i]['visible'] = $cm->visible;
	$activities[$i]['name'] = $cm->name;
       $activities[$i]['notes'] = array();

       $items = grade_get_grade_items_for_activity($cm);
        foreach ($items as $item)
        {
            $grade_grade = new grade_grade();
            $grades = $grade_grade->fetch_users_grades($item, $usersids);
            $j = 0;
            foreach ($grades as $userid => $grade)
            {
                $activities[$i]['notes'][$j]['nota'] = grade_format_gradevalue($grade->finalgrade, $item);
                $activities[$i]['notes'][$j]['notamax'] = round(grade_format_gradevalue($grade->rawgrademax, $item));
                $j++;
            }
        }
        $i++;
     }
     return $activities;
}

function EstudiantsCurs($courseid){
    $context = get_context_instance(CONTEXT_COURSE, $courseid);

    $estudiants = get_users_by_capability($context, 'moodle/legacy:student',

                                      'u.id, u.firstname, u.lastname', 'lastname, firstname',

                                      '', '', '' , '', false);

    return array_keys($estudiants);
}

function QualificacionsCursCategories($courseid, $userids){

    $resposta = array();
    $activitat_campus = array($courseid);
    $grade_grade = new grade_grade();
    $grade_category = new grade_category();
    $categories = $grade_category->fetch_all(array('courseid' => $courseid));


    foreach ($categories as $category) {

            $item = $category->get_grade_item();
            $grades = $grade_grade->fetch_users_grades($item, $userids);
            $activitats_modul[$category->id]['nom'] = $category->get_name();
            if ($category->is_course_category())
            {
				$activitats_modul[$category->id]['nom'] = "Global: ".$activitats_modul[$category->id]['nom'];
            }
            foreach ($grades as $userid => $grade)
            {
               // En aquest cas les notes estan en format cru, per tant cal obtenir-la en format text.
               $nota = grade_format_gradevalue($grade->finalgrade, $item);
               if (!isset($activitat_campus[$courseid][$userid])){
                 $activitat_campus[$courseid][$userid] = "#".$nota;
               } else {
                 $activitat_campus[$courseid][$userid] .= "#".$nota;
               }
               $qualificacions[$category->id][$userid] = $nota;
               if ($category->is_course_category())
               {
                   $qualificacions_global[$userid] = $nota;
               }
            } //grades

     } // Categories

     $resposta[0] = $qualificacions;
     $resposta[1] = $activitats_modul;
     $resposta[2] = $qualificacions_global;
     $resposta[3] = $activitat_campus;

     return $resposta;
}

function QualificacionsCursActivitats($courseid, $module, $userids){

    $activitats = grade_get_gradable_activities($courseid, $module);
    $grade_grade = new grade_grade();

    foreach ($activitats as $cm) {
        $items = grade_get_grade_items_for_activity($cm);
        foreach ($items as $item) {
            if ($cm->visible != "0"){
              $activitats_modul[$cm->id]['nom'] = $cm->name;
              //$activitats_modul[$cm->id]['nota_max'] = round(grade_format_gradevalue($grade->rawgrademax, $item));
              $activitats_modul[$cm->id]['nota_max'] = round(grade_format_gradevalue(null, $item));
              $grades = $grade_grade->fetch_users_grades($item, $userids);
              foreach ($grades as $userid => $grade) {
                // En aquest cas les notes estan en format cru, per tant cal obtenir-la en format text.
                $nota = grade_format_gradevalue($grade->finalgrade, $item);
                $qualificacions[$cm->id][$userid] = $nota;
              } //grades
         }//visible
       }//items
    } //activitats

     $resposta[0] = $qualificacions;
     $resposta[1] = $activitats_modul;

     return $resposta;
}
