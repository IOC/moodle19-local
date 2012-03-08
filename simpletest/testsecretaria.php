<?php

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/local/secretaria/lib.php");

require_once 'Mockery/Loader.php';
require_once 'Hamcrest/Hamcrest.php';

$loader = new Mockery\Loader;
$loader->register();

Mockery::getConfiguration()->allowMockingNonExistentMethods(false);

abstract class local_secretaria_test_base extends UnitTestCase {

    protected $moodle;
    protected $operations;

    function setUp() {
        $this->moodle = Mockery::mock('local_secretaria_moodle');
        $this->moodle->shouldReceive('get_context_id')->andReturn(false)->byDefault();
        $this->moodle->shouldReceive('get_course_id')->andReturn(false)->byDefault();
        $this->moodle->shouldReceive('get_group_id')->andReturn(false)->byDefault();
        $this->moodle->shouldReceive('get_role_id')->andReturn(false)->byDefault();
        $this->moodle->shouldReceive('get_user_id')->andReturn(false)->byDefault();
        $this->moodle->shouldReceive('get_user_record')->andReturn(false)->byDefault();
        $this->operations = new local_secretaria_operations($this->moodle);
    }

    function tearDown() {
        Mockery::close();
    }

    protected function having_context_id($shortname, $courseid) {
        $this->moodle->shouldReceive('get_context_id')
            ->with($shortname)->andReturn($courseid);
    }

    protected function having_course_id($shortname, $courseid) {
        $this->moodle->shouldReceive('get_course_id')
            ->with($shortname)->andReturn($courseid);
    }

    protected function having_group_id($courseid, $groupname, $groupid) {
        $this->moodle->shouldReceive('get_group_id')
            ->with($courseid, $groupname)->andReturn($groupid);
    }

    protected function having_role_id($shortname, $roleid) {
        $this->moodle->shouldReceive('get_role_id')
            ->with($shortname)->andReturn($roleid);
    }

    protected function having_user_id($username, $userid) {
        $this->moodle->shouldReceive('get_user_id')
            ->with($username)->andReturn($userid);
    }

    protected function having_user_record($username, $record) {
        $this->moodle->shouldReceive('get_user_record')
            ->with($username)->andReturn($record);
    }
}


class local_secretaria_test_valid_param extends UnitTestCase {

    function test_string() {
        $param = array('type' => 'string');
        $this->assertTrue(local_secretaria_service::valid_param($param, 'abc'));
        $this->assertFalse(local_secretaria_service::valid_param($param, ''));
        $this->assertFalse(local_secretaria_service::valid_param($param, true));
        $this->assertFalse(local_secretaria_service::valid_param($param, 123));
        $this->assertFalse(local_secretaria_service::valid_param($param, array()));
    }

    function test_string_optional() {
        $param = array('type' => 'string', 'optional' => true);
        $this->assertTrue(local_secretaria_service::valid_param($param, 'abc'));
        $this->assertTrue(local_secretaria_service::valid_param($param, ''));
    }

    function test_list() {
        $param = array('type' => 'list', 'of' => array('type' => 'string'));
        $this->assertTrue(local_secretaria_service::valid_param($param, array()));
        $this->assertTrue(local_secretaria_service::valid_param($param, array('abc', 'def')));
        $this->assertFalse(local_secretaria_service::valid_param($param, array('abc', 123)));
        $this->assertFalse(local_secretaria_service::valid_param($param, true));
        $this->assertFalse(local_secretaria_service::valid_param($param, 123));
    }

    function test_dictionary() {
        $param = array('type' => 'dictionary');
        $this->assertTrue(local_secretaria_service::valid_param($param, array()));
        $this->assertTrue(local_secretaria_service::valid_param($param, array('a' => 'A', 'b' => 'B')));
        $this->assertFalse(local_secretaria_service::valid_param($param, array('abc', 'def')));
        $this->assertFalse(local_secretaria_service::valid_param($param, array('a' => 1, 'b' => 2)));
        $this->assertFalse(local_secretaria_service::valid_param($param, true));
        $this->assertFalse(local_secretaria_service::valid_param($param, 123));
    }

    function test_dictionary_required() {
        $param = array('type' => 'dictionary', 'required' => array('a', 'b'));
        $this->assertTrue(local_secretaria_service::valid_param($param, array('a' => 'A', 'b' => 'B')));
        $this->assertFalse(local_secretaria_service::valid_param($param, array()));
        $this->assertFalse(local_secretaria_service::valid_param($param, array('a' => 'A')));
    }
}


/* Users */

class local_secretaria_test_get_user extends local_secretaria_test_base {

    function setUp() {
        parent::setUp();
        $this->record = (object) array(
            'id' => 123,
            'username' => 'user',
            'firstname' => 'First',
            'lastname' => 'Last',
            'email' => 'user@example.org',
            'picture' => 1,
        );
    }

    function test_get_user() {
        $this->having_user_record('user', $this->record);
        $this->moodle->shouldReceive('user_picture_url')->with(123)
            ->andReturn('http://example.org/user/pix.php/123/f1.jpg');

        $result = $this->operations->get_user('user');

        $this->assertEqual($result, array(
            'username' => 'user',
            'firstname' => 'First',
            'lastname' => 'Last',
            'email' => 'user@example.org',
            'picture' => 'http://example.org/user/pix.php/123/f1.jpg',
        ));
    }

    function test_no_picture() {
        $this->record->picture = 0;
        $this->having_user_record('user', $this->record);
        $this->moodle->shouldReceive('user_picture_url')->with(123)
            ->andReturn('http://example.org/user/pix.php/123/f1.jpg');

        $result = $this->operations->get_user('user');

        $this->assertNull($result['picture']);
    }

    function test_unknown_user() {
        $this->expectException(new local_secretaria_exception('Unknown user'));

        $this->operations->get_user('user');
    }
}


class local_secretaria_test_create_user extends local_secretaria_test_base {

    function setUp() {
        parent::setUp();
        $this->properties = array(
            'username' => 'user1',
            'firstname' => 'First',
            'lastname' => 'Last',
            'email' => 'user1@example.org',
            'password' => 'abc123',
        );
    }

    function test() {
        $this->moodle->shouldReceive('insert_user')
            ->with('user1', 'abc123', 'First', 'Last', 'user1@example.org')
            ->once()->andReturn(true);
        $this->operations->create_user($this->properties);
    }

    function test_duplicate_username() {
        $this->having_user_id('user1', 123);
        $this->expectException(new local_secretaria_exception('Duplicate username'));
        $this->operations->create_user($this->properties);
    }
}


class local_secretaria_test_update_user extends local_secretaria_test_base {

    function test() {
        $record = (object) array(
            'id' => 123,
            'username' => 'user2',
            'password' => hash_internal_user_password('abc123'),
            'firstname' => 'First2',
            'lastname' => 'Last2',
            'email' => 'user2@example.org',
        );
        $this->having_user_id('user1', 123);
        $this->having_user_id('user2', false);
        $this->moodle->shouldReceive('update_record')
            ->with('user', Mockery::mustBe($record))
            ->once()->andReturn(true);

        $this->operations->update_user('user1', array(
            'username' => 'user2',
            'password' => 'abc123',
            'firstname' => 'First2',
            'lastname' => 'Last2',
            'email' => 'user2@example.org',
        ));
    }

    function test_unknown_user() {
        $this->expectException(new local_secretaria_exception('Unknown user'));
        $this->operations->update_user('user1', array());
    }

    function test_duplicate_username() {
        $this->having_user_id('user1', 123);
        $this->having_user_id('user2', 456);
        $this->expectException(new local_secretaria_exception('Duplicate username'));
        $this->operations->update_user('user1', array('username' => 'user2'));
    }

    function test_username_not_changed() {
        $record = (object) array('id' => 123);
        $this->having_user_id('user1', 123);
        $this->moodle->shouldReceive('update_record')
            ->with('user', Mockery::mustBe($record))
            ->once()->andReturn(true);
        $this->operations->update_user('user1', array('username' => 'user1'));
    }
}


class local_secretaria_test_delete_user extends local_secretaria_test_base {

    function test() {
        $record = (object) array(
            'id' => 123,
            'username' => 'user1',
            '...' => '...',
        );

        $this->having_user_record('user1', $record);
        $this->moodle->shouldReceive('delete_user')
            ->with(Mockery::mustBe($record))
            ->once()->andReturn(true);

        $this->operations->delete_user('user1');
    }

    function test_unknown_user() {
        $this->expectException(new local_secretaria_exception('Unknown user'));
        $this->operations->delete_user('user1');
    }
}


/* Enrolments */

class local_secretaria_test_get_course_enrolments extends local_secretaria_test_base {

    function test() {
        $records = array(
            (object) array('id' => 456, 'user' => 'user1', 'role' => 'role1'),
            (object) array('id' => 789, 'user' => 'user2', 'role' => 'role2'),
        );

        $this->having_context_id('course1', 123);
        $this->moodle->shouldReceive('get_role_assignments_by_context')
            ->with(123)->andReturn($records);

        $result = $this->operations->get_course_enrolments('course1');

        $this->assertEqual($result, array(
            array('user' => 'user1', 'role' => 'role1'),
            array('user' => 'user2', 'role' => 'role2'),
        ));
    }

    function test_no_enrolments() {
        $this->having_context_id('course1', 123);
        $this->moodle->shouldReceive('get_role_assignments_by_context')
            ->with(123)->andReturn(array());

        $result = $this->operations->get_course_enrolments('course1');

        $this->assertEqual($result, array());
    }

    function test_unknown_course() {
        $this->expectException(new local_secretaria_exception('Unknown course'));
        $this->operations->get_course_enrolments('course1');
    }
}


class local_secretaria_test_get_user_enrolments extends local_secretaria_test_base {

    function test() {
        $records = array(
            (object) array('id' => 456, 'course' => 'course1', 'role' => 'role1'),
            (object) array('id' => 789, 'course' => 'course2', 'role' => 'role2'),
        );
        $this->having_user_id('user1', 123);
        $this->moodle->shouldReceive('get_role_assignments_by_user')
            ->with(123)->andReturn($records);

        $result = $this->operations->get_user_enrolments('user1');

        $this->assertEqual($result, array(
            array('course' => 'course1', 'role' => 'role1'),
            array('course' => 'course2', 'role' => 'role2'),
        ));
    }

    function test_no_enrolments() {
        $this->having_user_id('user1', 123);
        $this->moodle->shouldReceive('get_role_assignments_by_user')
            ->with(123)->andReturn(array());

        $result = $this->operations->get_user_enrolments('user1');

        $this->assertEqual($result, array());
    }

    function test_unknown_user() {
        $this->expectException(new local_secretaria_exception('Unknown user'));
        $this->operations->get_user_enrolments('user1');
    }
}


class local_secretaria_test_enrol_users extends local_secretaria_test_base {

    function test() {
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        for ($i = 1; $i <= 3; $i++) {
            $this->having_context_id('course' . $i, 100 + $i);
            $this->having_user_id('user' . $i, 200 + $i);
            $this->having_role_id('role' . $i, 300 + $i);
            $this->moodle->shouldReceive('role_assignment_exists')
                ->with(100 + $i, 200 + $i, 300 + $i)->andReturn(false);
            $this->moodle->shouldReceive('insert_role_assignment')
                ->with(100 + $i, 200 + $i, 300 + $i)->andReturn(true)
                ->once()->ordered();
        }
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
            array('course' => 'course2', 'user' => 'user2', 'role' => 'role2'),
            array('course' => 'course3', 'user' => 'user3', 'role' => 'role3'),
        ));
    }

    function test_duplicate_enrolment() {
        $this->having_context_id('course1', 101);
        $this->having_user_id('user1', 201);
        $this->having_role_id('role1', 301);
        $this->moodle->shouldReceive('role_assignment_exists')
                ->with(101, 201, 301)->andReturn(true);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }

    function test_unknown_course() {
        $this->having_user_id('user1', 201);
        $this->having_role_id('role1', 301);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('rollback_transaction')->once()->ordered();

        $this->expectException(new local_secretaria_exception('Unknown course'));
        $this->operations->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }

    function test_unknown_user() {
        $this->having_context_id('course1', 101);
        $this->having_role_id('role1', 301);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('rollback_transaction')->once()->ordered();

        $this->expectException(new local_secretaria_exception('Unknown user'));
        $this->operations->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }

    function test_unknown_role() {
        $this->having_context_id('course1', 101);
        $this->having_user_id('user1', 201);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('rollback_transaction')->once()->ordered();

        $this->expectException(new local_secretaria_exception('Unknown role'));
        $this->operations->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }
}


class local_secretaria_test_unenrol_users extends local_secretaria_test_base {

    function test() {
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        for ($i = 1; $i <= 3; $i++) {
            $this->having_context_id('course' . $i, 100 + $i);
            $this->having_user_id('user' . $i, 200 + $i);
            $this->having_role_id('role' . $i, 300 + $i);
            $this->moodle->shouldReceive('role_assignment_exists')
                ->with(100 + $i, 200 + $i, 300 + $i)->andReturn(false);
            $this->moodle->shouldReceive('delete_role_assignment')
                ->with(100 + $i, 200 + $i, 300 + $i)->andReturn(true)
                ->once()->ordered();
        }
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->unenrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
            array('course' => 'course2', 'user' => 'user2', 'role' => 'role2'),
            array('course' => 'course3', 'user' => 'user3', 'role' => 'role3'),
        ));
    }

    function test_unknown_course() {
        $this->having_user_id('user1', 201);
        $this->having_role_id('role1', 301);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('rollback_transaction')->once()->ordered();

        $this->expectException(new local_secretaria_exception('Unknown course'));
        $this->operations->unenrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
       ));
    }

    function test_unknown_user() {
        $this->having_context_id('course1', 101);
        $this->having_role_id('role1', 301);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('rollback_transaction')->once()->ordered();

        $this->expectException(new local_secretaria_exception('Unknown user'));
        $this->operations->unenrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }

    function test_unknown_role() {
        $this->having_context_id('course1', 101);
        $this->having_user_id('user1', 201);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('rollback_transaction')->once()->ordered();

        $this->expectException(new local_secretaria_exception('Unknown role'));
        $this->operations->unenrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }
}


/* Groups */

class local_secretaria_test_get_groups extends local_secretaria_test_base {

    function test() {
        $records = array(
            (object) array('id' => 201,
                           'name' => 'group1',
                           'description' => 'first group'),
            (object) array('id' => 202,
                           'name' => 'group2',
                           'description' => 'second group'),
        );
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_groups')
            ->with(101)->andReturn($records);

        $result = $this->operations->get_groups('course1');

        $this->assertEqual($result, array(
            array('name' => 'group1', 'description' => 'first group'),
            array('name' => 'group2', 'description' => 'second group'),
        ));
    }

    function test_no_groups() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_groups')
            ->with(101)->andReturn(false);

        $result = $this->operations->get_groups('course1');

        $this->assertEqual($result, array());
    }

    function test_unknown_course() {
        $this->expectException(new local_secretaria_exception('Unknown course'));
        $this->operations->get_groups('course1');
    }
}


class local_secretaria_test_create_group extends local_secretaria_test_base {

    function test() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('insert_group')
            ->with(101, 'group1', 'Group 1')
            ->once()->andReturn(true);

        $this->operations->create_group('course1', 'group1', 'Group 1');
    }

    function test_unknown_course() {
        $this->expectException(new local_secretaria_exception('Unknown course'));
        $this->operations->create_group('course1', 'group1', 'Group 1');
    }

    function test_duplicate_group() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);

        $this->expectException(new local_secretaria_exception('Duplicate group'));
        $this->operations->create_group('course1', 'group1', 'Group 1');
    }
}


class local_secretaria_test_delete_group extends local_secretaria_test_base {

    function test() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->moodle->shouldReceive('groups_delete_group')
            ->with(201)->andReturn(true)->once();

        $this->operations->delete_group('course1', 'group1');
    }

    function test_unknown_course() {
        $this->expectException(new local_secretaria_exception('Unknown course'));
        $this->operations->delete_group('course1', 'group1');
    }

    function test_unknown_group() {
        $this->having_course_id('course1', 101);
        $this->expectException(new local_secretaria_exception('Unknown group'));
        $this->operations->delete_group('course1', 'group1');
    }
}


class local_secretaria_test_get_group_members extends local_secretaria_test_base {

    function test() {
        $records = array(
            (object) array('id' => 301, 'username' => 'user1'),
            (object) array('id' => 302, 'username' => 'user2'),
        );
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->moodle->shouldReceive('get_group_members')
            ->with(201)->andReturn($records);

        $result = $this->operations->get_group_members('course1', 'group1');

        $this->assertEqual($result, array('user1', 'user2'));
    }

    function test_no_members() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->moodle->shouldReceive('get_group_members')
            ->with(201)->andReturn(false);

        $result = $this->operations->get_group_members('course1', 'group1');

        $this->assertEqual($result, array());
    }

    function test_unknown_course() {
        $this->expectException(new local_secretaria_exception('Unknown course'));
        $this->operations->get_group_members('course1', 'group1');
    }

    function test_unknown_group() {
        $this->having_course_id('course1', 101);
        $this->expectException(new local_secretaria_exception('Unknown group'));
        $this->operations->get_group_members('course1', 'group1');
    }
}


class local_secretaria_test_add_group_members extends local_secretaria_test_base {

    function test() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->having_user_id('user1', 301);
        $this->having_user_id('user2', 302);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('groups_add_member')
            ->with(201, 301)->andReturn(true)->once()->ordered();
        $this->moodle->shouldReceive('groups_add_member')
            ->with(201, 302)->andReturn(true)->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->add_group_members('course1', 'group1', array('user1', 'user2'));
    }

    function test_unknown_course() {
        $this->expectException(new local_secretaria_exception('Unknown course'));
        $this->operations->add_group_members('course1', 'group1', array());
    }

    function test_unknown_group() {
        $this->having_course_id('course1', 101);
        $this->expectException(new local_secretaria_exception('Unknown group'));
        $this->operations->add_group_members('course1', 'group1', array());
    }

    function test_unknown_user() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('rollback_transaction')->once()->ordered();

        $this->expectException(new local_secretaria_exception('Unknown user'));
        $this->operations->add_group_members('course1', 'group1', array('user1'));
    }
}


class local_secretaria_test_remove_group_members extends local_secretaria_test_base {

    function test() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->having_user_id('user1', 301);
        $this->having_user_id('user2', 302);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('groups_remove_member')
            ->with(201, 301)->once()->ordered();
        $this->moodle->shouldReceive('groups_remove_member')
            ->with(201, 302)->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $result = $this->operations->remove_group_members(
            'course1', 'group1', array('user1', 'user2'));
    }

    function test_unknown_course() {
        $this->expectException(new local_secretaria_exception('Unknown course'));
        $this->operations->remove_group_members('course1', 'group1', array());
    }

    function test_unknown_group() {
        $this->having_course_id('course1', 101);
        $this->expectException(new local_secretaria_exception('Unknown group'));
        $this->operations->remove_group_members('course1', 'group1', array());
    }

    function test_unknown_user() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('rollback_transaction')->once()->ordered();

        $this->expectException(new local_secretaria_exception('Unknown user'));
        $this->operations->remove_group_members('course1', 'group1', array('user1'));
    }
}


/* Grades */

class local_secretaria_test_get_course_grades extends local_secretaria_test_base {

    function test() {
        $grade_items = array(
            (object) array(
                'itemtype' => 'course',
                'itemmodule' => null,
                'iteminstance' => 101,
                'itemname' => null,
                'idnumber' => 'id101',
            ),
            (object) array(
                'itemtype' => 'category',
                'itemmodule' => null,
                'iteminstance' => 301,
                'itemname' => 'Category 1',
                'idnumber' => 'id301',
            ),
            (object) array(
                'itemtype' => 'module',
                'itemmodule' => 'assignment',
                'iteminstance' => 401,
                'itemname' => 'Assignment 1',
                'idnumber' => 'id401',
            ),
        );
        $grades_course = array(
            201 => (object) array('str_grade' => '5.1'),
            202 => (object) array('str_grade' => '5.2'),
        );
        $grades_category = array(
            201 => (object) array('str_grade' => '6.1'),
            202 => (object) array('str_grade' => '6.2'),
        );
        $grades_module = array(
            201 => (object) array('str_grade' => '7.1'),
            202 => (object) array('str_grade' => '7.2'),
        );

        $this->having_course_id('course1', 101);
        $this->having_user_id('user1', 201);
        $this->having_user_id('user2', 202);
        $this->moodle->shouldReceive('grade_item_fetch_all')
            ->with(101)->andReturn($grade_items);
        $this->moodle->shouldReceive('grade_get_grades')
            ->with(101, 'course', null, 101, array(201, 202))
            ->andReturn($grades_course);
        $this->moodle->shouldReceive('grade_get_grades')
            ->with(101, 'category', null, 301, array(201, 202))
            ->andReturn($grades_category);
        $this->moodle->shouldReceive('grade_get_grades')
            ->with(101, 'module', 'assignment', 401, array(201, 202))
            ->andReturn($grades_module);

        $result = $this->operations->get_course_grades(
            'course1', array('user1', 'user2'));

        $this->assertEqual($result, array(
            array('type' => 'course',
                  'module' => null,
                  'idnumber' => 'id101',
                  'name' => null,
                  'grades' => array('user1' => '5.1', 'user2' => '5.2')),
            array('type' => 'category',
                  'module' => null,
                  'idnumber' => 'id301',
                  'name' => 'Category 1',
                  'grades' => array('user1' => '6.1', 'user2' => '6.2')),
            array('type' => 'module',
                  'module' => 'assignment',
                  'idnumber' => 'id401',
                  'name' => 'Assignment 1',
                  'grades' => array('user1' => '7.1', 'user2' => '7.2')),
        ));
    }

    function test_no_grade_items() {
        $this->having_course_id('course1', 101);
        $this->having_user_id('user1', 201);
        $this->having_user_id('user2', 202);
        $this->moodle->shouldReceive('grade_item_fetch_all')
            ->with(101)->andReturn(false);

        $result = $this->operations->get_course_grades(
            'course1', array('user1', 'user2'));

        $this->assertIdentical($result, array());
    }

    function test_unknown_course() {
        $this->expectException(new local_secretaria_exception('Unknown course'));
        $this->operations->get_course_grades('course1', array('user1', 'user2'));
    }

    function test_unknown_user() {
        $this->having_course_id('course1', 101);
        $this->expectException(new local_secretaria_exception('Unknown user'));
        $this->operations->get_course_grades('course1', array('user1', 'user2'));
    }
}


class local_secretaria_test_get_user_grades extends local_secretaria_test_base {

    function test() {
        $grade1 = (object) array('str_grade' => '5.1');
        $grade2 = (object) array('str_grade' => '6.2');

        $this->having_course_id('course1', 101);
        $this->having_course_id('course2', 102);
        $this->having_user_id('user1', 201);
        $this->moodle->shouldReceive('grade_get_course_grade')
            ->with(201, 101)->andReturn($grade1);
        $this->moodle->shouldReceive('grade_get_course_grade')
            ->with(201, 102)->andReturn($grade2);

        $result = $this->operations->get_user_grades(
            'user1', array('course1', 'course2'));

        $this->assertEqual($result, array(
            'course1' => '5.1',
            'course2' => '6.2',
        ));
    }

    function test_no_grade() {
        $this->having_user_id('user1', 201);
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('grade_get_course_grade')
            ->with(201, 101)->andReturn(false);

        $result = $this->operations->get_user_grades('user1', array('course1'));

        $this->assertIdentical($result, array('course1' => null));
    }

    function test_unknown_course() {
        $this->having_user_id('user1', 201);
        $this->expectException(new local_secretaria_exception('Unknown course'));
        $this->operations->get_user_grades('user1', array('course1', 'course2'));
    }

    function test_unknown_user() {
        $this->expectException(new local_secretaria_exception('Unknown user'));
        $this->operations->get_user_grades('user1', array());
    }
}
