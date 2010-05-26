<?php

class batch_type_fix_quizzes extends batch_type_base {

    function execute($params) {
        $quizzes = get_records('quiz', '', '', '', 'id, questions');
        $quizzes = $quizzes ? $quizzes : array();
        foreach ($quizzes as $quiz) {
            $instances = array();
            $questions = array();

            $records = get_records('quiz_question_instances',
                                   'quiz', $quiz->id);
            $records = $records ? $records : array();
            foreach ($records as $record) {
                $instances[] = $record->question;
            }

            foreach (explode(",",  $quiz->questions) as $question) {
                if ($question and in_array($question, $instances)) {
                    $questions[] = $question;
                }
            }

            foreach ($instances as $question) {
                if (!in_array($question, $questions)) {
                    $questions[] = $question;
                }
            }

            $questions[] = 0;
            set_field('quiz', 'questions', implode(",", $questions),
                      'id', $quiz->id);
        }
    }

}
