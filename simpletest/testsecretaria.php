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
    protected $mockcfg;
    protected $realcfg;
    protected $service;

    function having_course_id($shortname, $courseid) {
        $this->moodle->shouldReceive('get_course_id')
            ->with($shortname)->andReturn($courseid);
    }

    function having_context_id($shortname, $courseid) {
        $this->moodle->shouldReceive('get_context_id')
            ->with($shortname)->andReturn($courseid);
    }

    function having_group_id($courseid, $groupname, $groupid) {
        $this->moodle->shouldReceive('get_group_id')
            ->with($courseid, $groupname)->andReturn($groupid);
    }

    function having_role_id($shortname, $roleid) {
        $this->moodle->shouldReceive('get_role_id')
            ->with($shortname)->andReturn($roleid);
    }

    function having_user_id($username, $userid) {
        $this->moodle->shouldReceive('get_user_id')
            ->with($username)->andReturn($userid);
    }

    function having_user_record($username, $record) {
        $this->moodle->shouldReceive('get_user_record')
            ->with($username)->andReturn($record);
    }

    function setUp() {
        global $CFG;
        $this->realcfg = $CFG;
        $this->cfg = $CFG = new stdClass;

        $this->moodle = Mockery::mock('local_secretaria_moodle');
        $this->service = new local_secretaria_service($this->moodle);

        $this->moodle->shouldReceive('get_context_id')
            ->andReturn(false)->byDefault();
        $this->moodle->shouldReceive('get_course_id')
            ->andReturn(false)->byDefault();
        $this->moodle->shouldReceive('get_group_id')
            ->andReturn(false)->byDefault();
        $this->moodle->shouldReceive('get_role_id')
            ->andReturn(false)->byDefault();
        $this->moodle->shouldReceive('get_user_id')
            ->andReturn(false)->byDefault();
        $this->moodle->shouldReceive('get_user_record')
            ->andReturn(false)->byDefault();
    }

    function tearDown() {
        global $CFG;
        $CFG = $this->realcfg;
        Mockery::close();
    }
}

/* Users */

class local_secretaria_test_get_user extends local_secretaria_test_base {

    function setUp() {
        parent::setUp();
        $this->cfg->wwwroot = 'http://example.org';
        $this->record = (object) array(
            'id' => 123,
            'username' => 'user',
            'firstname' => 'First',
            'lastname' => 'Last',
            'email' => 'user@example.org',
            'picture' => 1,
        );
    }

    function test_return_properties_if_user_exists() {
        $this->having_user_record('user', $this->record);

        $result = $this->service->get_user('user');

        $this->assertEqual($result, array(
            'username' => 'user',
            'firstname' => 'First',
            'lastname' => 'Last',
            'email' => 'user@example.org',
            'picture' => "http://example.org/user/pix.php/123/f1.jpg",
        ));
    }

    function test_fail_if_user_does_not_exist() {
        $result = $this->service->get_user('user');

        $this->assertNull($result);
    }

    function test_return_null_picture_if_user_does_not_have_one() {
        $this->record->picture = 0;
        $this->having_user_record('user', $this->record);

        $result = $this->service->get_user('user');

        $this->assertNull($result['picture']);
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

    function test_insert_user_record() {
        $this->moodle->shouldReceive('insert_user')
            ->with('user1', 'abc123', 'First', 'Last', 'user1@example.org')
            ->once()->andReturn(true);

        $result = $this->service->create_user($this->properties);

        $this->assertTrue($result);
    }

    function test_fail_if_email_is_missing() {
        unset($this->properties['email']);
        $result = $this->service->create_user($this->properties);
        $this->assertFalse($result);
    }

    function test_fail_if_firstname_is_missing() {
        unset($this->properties['firstname']);
        $result = $this->service->create_user($this->properties);
        $this->assertFalse($result);
    }

    function test_fail_if_lastname_is_missing() {
        unset($this->properties['lastname']);
        $result = $this->service->create_user($this->properties);
        $this->assertFalse($result);
    }

    function test_fail_if_password_is_missing() {
        unset($this->properties['password']);
        $result = $this->service->create_user($this->properties);
        $this->assertFalse($result);
    }

    function test_fail_if_user_already_exists() {
        $this->having_user_id('user1', 123);
        $result = $this->service->create_user($this->properties);
        $this->assertFalse($result);
    }

    function test_fail_if_username_is_missing() {
        unset($this->properties['username']);
        $result = $this->service->create_user($this->properties);
        $this->assertFalse($result);
    }
}

class local_secretaria_test_update_user extends local_secretaria_test_base {

    function test_fail_if_update_fails() {
        $record = (object) array('id' => 123);
        $this->moodle->shouldReceive('get_user_id')
            ->with('user1')->andReturn(123);
        $this->moodle->shouldReceive('update_record')
            ->with('user', Mockery::mustBe($record))
            ->once()->andReturn(false);

        $result = $this->service->update_user('user1', array());

        $this->assertFalse($result);
    }

    function test_fail_if_user_does_not_exist() {
        $this->moodle->shouldReceive('get_user_id')
            ->with('user1')->andReturn(false);
        $result = $this->service->update_user('user1', array());
        $this->assertFalse($result);
    }

    function test_fail_if_username_already_exists() {
        $this->moodle->shouldReceive('get_user_id')
            ->with('user1')->andReturn(123);
       $this->moodle->shouldReceive('get_user_id')
            ->with('user2')->andReturn(456);

        $result = $this->service->update_user('user1', array(
            'username' => 'user2',
        ));

        $this->assertFalse($result);
    }

    function test_ignore_username_if_not_changed() {
        $record = (object) array('id' => 123);
        $this->moodle->shouldReceive('get_user_id')
            ->with('user1')->andReturn(123);
        $this->moodle->shouldReceive('update_record')
            ->with('user', Mockery::mustBe($record))
            ->once()->andReturn(true);

        $result = $this->service->update_user('user1', array(
            'username' => 'user1',
        ));

        $this->assertTrue($result);
    }

    function test_update_user_record() {
        $record = (object) array(
            'id' => 123,
            'username' => 'user2',
            'password' => hash_internal_user_password('abc123'),
            'firstname' => 'First2',
            'lastname' => 'Last2',
            'email' => 'user2@example.org',
        );
        $this->moodle->shouldReceive('get_user_id')
            ->with('user1')->andReturn(123);
        $this->moodle->shouldReceive('get_user_id')
            ->with('user2')->andReturn(false);
        $this->moodle->shouldReceive('update_record')
            ->with('user', Mockery::mustBe($record))
            ->once()->andReturn(true);

        $result = $this->service->update_user('user1', array(
            'username' => 'user2',
            'password' => 'abc123',
            'firstname' => 'First2',
            'lastname' => 'Last2',
            'email' => 'user2@example.org',
        ));

        $this->assertTrue($result);
    }
}

class local_secretaria_test_delete_user extends local_secretaria_test_base {

    function test_delete_user() {
        $record = (object) array(
            'id' => 123,
            'username' => 'user1',
            '...' => '...',
        );

        $this->having_user_record('user1', $record);
        $this->moodle->shouldReceive('delete_user')
            ->with(Mockery::mustBe($record))
            ->once()->andReturn(true);

        $result = $this->service->delete_user('user1');

        $this->assertTrue($result);
    }

    function test_fail_if_user_does_not_exist() {
        $result = $this->service->delete_user('user1', array());

        $this->assertFalse($result);
    }
}

/* Enrolments */

class local_secretaria_test_get_course_enrolments extends local_secretaria_test_base {

    function test_fail_if_course_does_not_exist() {
        $result = $this->service->get_course_enrolments('course1');
        $this->assertNull($result);
    }

    function test_return_empty_array_if_no_enrolments() {
        $this->having_context_id('course1', 123);
        $this->moodle->shouldReceive('get_role_assignments_by_context')
            ->with(123)->andReturn(array());

        $result = $this->service->get_course_enrolments('course1');

        $this->assertEqual($result, array());
    }

    function test_return_enrolments() {
        $records = array(
            (object) array('id' => 456, 'user' => 'user1', 'role' => 'role1'),
            (object) array('id' => 789, 'user' => 'user2', 'role' => 'role2'),
        );

        $this->having_context_id('course1', 123);
        $this->moodle->shouldReceive('get_role_assignments_by_context')
            ->with(123)->andReturn($records);

        $result = $this->service->get_course_enrolments('course1');

        $this->assertEqual($result, array(
            array('user' => 'user1', 'role' => 'role1'),
            array('user' => 'user2', 'role' => 'role2'),
        ));
    }
}

class local_secretaria_test_get_user_enrolments extends local_secretaria_test_base {

    function test_fail_if_course_does_not_exist() {
        $result = $this->service->get_user_enrolments('user1');
        $this->assertNull($result);
    }

    function test_return_empty_array_if_no_enrolments() {
        $this->having_user_id('user1', 123);
        $this->moodle->shouldReceive('get_role_assignments_by_user')
            ->with(123)->andReturn(array());

        $result = $this->service->get_user_enrolments('user1');

        $this->assertEqual($result, array());
    }

    function test_return_enrolments() {
        $records = array(
            (object) array('id' => 456, 'course' => 'course1', 'role' => 'role1'),
            (object) array('id' => 789, 'course' => 'course2', 'role' => 'role2'),
        );
        $this->having_user_id('user1', 123);
        $this->moodle->shouldReceive('get_role_assignments_by_user')
            ->with(123)->andReturn($records);

        $result = $this->service->get_user_enrolments('user1');

        $this->assertEqual($result, array(
            array('course' => 'course1', 'role' => 'role1'),
            array('course' => 'course2', 'role' => 'role2'),
        ));
    }
}

class local_secretaria_test_enrol_users extends local_secretaria_test_base {

    function test_ignore_existent_enrolment() {
        $this->having_context_id('course1', 101);
        $this->having_user_id('user1', 201);
        $this->having_role_id('role1', 301);
        $this->moodle->shouldReceive('role_assignment_exists')
                ->with(101, 201, 301)->andReturn(true);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $result = $this->service->enrol_users(array(
            array('course' => 'course1',
                  'user' => 'user1',
                  'role' => 'role1'),
        ));

        $this->assertTrue($result);
    }

    function test_insert_role_assignments() {
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

        $result = $this->service->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
            array('course' => 'course2', 'user' => 'user2', 'role' => 'role2'),
            array('course' => 'course3', 'user' => 'user3', 'role' => 'role3'),
        ));

        $this->assertTrue($result);
    }

    function test_rollback_if_context_does_not_exist() {
        $this->having_user_id('user1', 201);
        $this->having_role_id('role1', 301);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('rollback_transaction')->once()->ordered();

        $result = $this->service->enrol_users(array(
            array('course' => 'course1',
                  'user' => 'user1',
                  'role' => 'role1'),
        ));

        $this->assertFalse($result);
    }

    function test_rollback_if_insert_fails() {
        $this->having_context_id('course1', 101);
        $this->having_user_id('user1', 201);
        $this->having_role_id('role1', 301);
        $this->moodle->shouldReceive('role_assignment_exists')
            ->with(101, 201, 301)->andReturn(false);

        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('insert_role_assignment')
            ->with(101, 201, 301)->andReturn(false)
            ->once()->ordered();
        $this->moodle->shouldReceive('rollback_transaction')->once()->ordered();

        $result = $this->service->enrol_users(array(
            array('course' => 'course1',
                  'user' => 'user1',
                  'role' => 'role1'),
        ));

        $this->assertFalse($result);
    }

    function test_rollback_if_user_does_not_exist() {
        $this->having_context_id('course1', 101);
        $this->having_role_id('role1', 301);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('rollback_transaction')->once()->ordered();

        $result = $this->service->enrol_users(array(
            array('course' => 'course1',
                  'user' => 'user1',
                  'role' => 'role1'),
        ));

        $this->assertFalse($result);
    }

    function test_rollback_if_role_does_not_exist() {
        $this->having_context_id('course1', 101);
        $this->having_user_id('user1', 201);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('rollback_transaction')->once()->ordered();

        $result = $this->service->enrol_users(array(
            array('course' => 'course1',
                  'user' => 'user1',
                  'role' => 'role1'),
        ));

        $this->assertFalse($result);
    }
}

class local_secretaria_test_unenrol_users extends local_secretaria_test_base {

    function test_delete_role_assignments() {
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

        $result = $this->service->unenrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
            array('course' => 'course2', 'user' => 'user2', 'role' => 'role2'),
            array('course' => 'course3', 'user' => 'user3', 'role' => 'role3'),
        ));

        $this->assertTrue($result);
    }

    function test_rollback_if_context_does_not_exist() {
        $this->having_user_id('user1', 201);
        $this->having_role_id('role1', 301);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('rollback_transaction')->once()->ordered();

        $result = $this->service->unenrol_users(array(
            array('course' => 'course1',
                  'user' => 'user1',
                  'role' => 'role1'),
        ));

        $this->assertFalse($result);
    }

    function test_rollback_user_does_not_exist() {
        $this->having_context_id('course1', 101);
        $this->having_role_id('role1', 301);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('rollback_transaction')->once()->ordered();

        $result = $this->service->unenrol_users(array(
            array('course' => 'course1',
                  'user' => 'user1',
                  'role' => 'role1'),
        ));

        $this->assertFalse($result);
    }

    function test_rollback_if_role_does_not_exist() {
        $this->having_context_id('course1', 101);
        $this->having_user_id('user1', 201);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('rollback_transaction')->once()->ordered();

        $result = $this->service->unenrol_users(array(
            array('course' => 'course1',
                  'user' => 'user1',
                  'role' => 'role1'),
        ));

        $this->assertFalse($result);
    }
}

/* Groups */

class local_secretaria_test_get_groups extends local_secretaria_test_base {

    function test_fail_if_course_does_not_exist() {
        $result = $this->service->get_groups('course1');
        $this->assertNull($result);
    }

    function test_return_groups() {
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

        $result = $this->service->get_groups('course1');

        $this->assertEqual($result, array(
            array('name' => 'group1', 'description' => 'first group'),
            array('name' => 'group2', 'description' => 'second group'),
        ));
    }

    function test_return_empty_array_if_no_groups() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_groups')
            ->with(101)->andReturn(false);

        $result = $this->service->get_groups('course1');

        $this->assertEqual($result, array());
    }
}


class local_secretaria_test_create_group extends local_secretaria_test_base {

    function setUp() {
        parent::setUp();
        $this->moodle->shouldReceive('get_course_id')
            ->with('course1')->andReturn(101)
            ->byDefault();
        $this->moodle->shouldReceive('get_group_id')
            ->with(101, 'group1')->andReturn(false)
            ->byDefault();
    }

    function test_create_group() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('insert_group')
            ->with(101, 'group1', 'Group 1')
            ->once()->andReturn(true);

        $result = $this->service->create_group('course1', 'group1', 'Group 1');

        $this->assertTrue($result);
    }

    function test_fail_if_course_does_not_exist() {
        $result = $this->service->create_group('course1', 'group1', 'Group 1');
        $this->assertFalse($result);
    }

    function test_fail_if_group_exists() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);

        $result = $this->service->create_group('course1', 'group1', 'Group 1');

        $this->assertFalse($result);
    }

    function test_fail_if_insert_fails() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('insert_group')
            ->with(101, 'group1', 'Group 1')
            ->once()->andReturn(false);

        $result = $this->service->create_group('course1', 'group1', 'Group 1');

        $this->assertFalse($result);
    }
}


class local_secretaria_test_delete_group extends local_secretaria_test_base {

    function test_fail_if_course_does_not_exist() {
        $result = $this->service->delete_group('course1', 'group1');
        $this->assertFalse($result);
    }

    function test_fail_if_group_does_not_exist() {
        $this->having_course_id('course1', 101);
        $result = $this->service->delete_group('course1', 'group1');
        $this->assertFalse($result);
    }

    function test_delete_group() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->moodle->shouldReceive('groups_delete_group')
            ->with(201)->andReturn(true)->once();

        $result = $this->service->delete_group('course1', 'group1');

        $this->assertTrue($result);
    }
}


class local_secretaria_test_get_group_members extends local_secretaria_test_base {

    function test_fail_if_course_does_not_exist() {
        $result = $this->service->get_group_members('course1', 'group1');
        $this->assertNull($result);
    }

    function test_fail_if_group_does_not_exist() {
        $this->having_course_id('course1', 101);
        $result = $this->service->get_group_members('course1', 'group1');
        $this->assertNull($result);
    }

    function test_return_group_members() {
        $records = array(
            (object) array('id' => 301, 'username' => 'user1'),
            (object) array('id' => 302, 'username' => 'user2'),
        );
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->moodle->shouldReceive('get_group_members')
            ->with(201)->andReturn($records);

        $result = $this->service->get_group_members('course1', 'group1');

        $this->assertEqual($result, array('user1', 'user2'));
    }

    function test_return_empty_array_if_no_members() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->moodle->shouldReceive('get_group_members')
            ->with(201)->andReturn(false);

        $result = $this->service->get_group_members('course1', 'group1');

        $this->assertEqual($result, array());
    }
}


class local_secretaria_test_add_group_members extends local_secretaria_test_base {

    function test_add_group_members() {
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

        $result = $this->service->add_group_members(
            'course1', 'group1', array('user1', 'user2'));

        $this->assertTrue($result);
    }

    function test_fail_if_course_does_not_exist() {
        $result = $this->service->add_group_members(
            'course1', 'group1', array('user1', 'user2'));
        $this->assertFalse($result);
    }

    function test_fail_if_group_does_not_exist() {
        $this->having_course_id('course1', 101);
        $result = $this->service->add_group_members(
            'course1', 'group1', array('user1', 'user2'));
        $this->assertFalse($result);
    }

    function test_rollback_if_user_does_not_exist() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('rollback_transaction')->once()->ordered();

        $result = $this->service->add_group_members(
            'course1', 'group1', array('user1'));

        $this->assertFalse($result);
    }

    function test_rollback_if_addition_fails() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->having_user_id('user1', 301);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('groups_add_member')
            ->with(201, 301)->andReturn(false)->once()->ordered();
        $this->moodle->shouldReceive('rollback_transaction')->once()->ordered();

        $result = $this->service->add_group_members(
            'course1', 'group1', array('user1'));

        $this->assertFalse($result);
    }
}


class local_secretaria_test_remove_group_members extends local_secretaria_test_base {

    function test_fail_if_course_does_not_exist() {
        $result = $this->service->remove_group_members(
            'course1', 'group1', array('user1', 'user2'));
        $this->assertFalse($result);
    }

    function test_fail_if_group_does_not_exist() {
        $this->having_course_id('course1', 101);
        $result = $this->service->remove_group_members(
            'course1', 'group1', array('user1', 'user2'));
        $this->assertFalse($result);
    }

    function test_remove_group_members() {
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

        $result = $this->service->remove_group_members(
            'course1', 'group1', array('user1', 'user2'));

        $this->assertTrue($result);
    }

    function test_rollback_if_user_does_not_exist() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('rollback_transaction')->once()->ordered();

        $result = $this->service->remove_group_members(
            'course1', 'group1', array('user1'));

        $this->assertFalse($result);
    }
}

/* Grades */

class local_secretaria_test_get_course_grades extends local_secretaria_test_base {

    function test_return_null_if_course_does_not_exist() {
        $result = $this->service->get_course_grades(
            'course1', array('user1', 'user2'));
        $this->assertNull($result);
    }

    function test_return_null_if_no_grade_items() {
        $this->having_course_id('course1', 101);
        $this->having_user_id('user1', 201);
        $this->having_user_id('user2', 202);
        $this->moodle->shouldReceive('grade_item_fetch_all')
            ->with(101)->andReturn(false);

        $result = $this->service->get_course_grades(
            'course1', array('user1', 'user2'));

        $this->assertNull($result);
    }

    function test_return_null_if_user_does_not_exist() {
        $this->having_course_id('course1', 101);
        $result = $this->service->get_course_grades(
            'course1', array('user1', 'user2'));
        $this->assertNull($result);
    }

    function test_return_course_grades() {
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

        $result = $this->service->get_course_grades(
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
}

class local_secretaria_test_get_user_grades extends local_secretaria_test_base {

    function test_return_null_if_course_does_not_exist() {
        $this->having_user_id('user1', 201);
        $result = $this->service->get_user_grades(
            'user1', array('course1', 'course2'));
        $this->assertNull($result);
    }

    function test_return_null_if_no_grade() {
        $this->having_course_id('course1', 101);
        $this->having_user_id('user1', 201);
        $this->moodle->shouldReceive('grade_get_course_grade')
            ->with(201, 101)->andReturn(false);

        $result = $this->service->get_user_grades('user1', array('course1'));

        $this->assertNull($result);
    }

    function test_return_null_if_user_does_not_exist() {
        $result = $this->service->get_user_grades('user1', array());
        $this->assertNull($result);
    }

    function test_return_user_grades() {
        $grade1 = (object) array('str_grade' => '5.1');
        $grade2 = (object) array('str_grade' => '6.2');

        $this->having_course_id('course1', 101);
        $this->having_course_id('course2', 102);
        $this->having_user_id('user1', 201);
        $this->moodle->shouldReceive('grade_get_course_grade')
            ->with(201, 101)->andReturn($grade1);
        $this->moodle->shouldReceive('grade_get_course_grade')
            ->with(201, 102)->andReturn($grade2);

        $result = $this->service->get_user_grades(
            'user1', array('course1', 'course2'));

        $this->assertEqual($result, array(
            'course1' => '5.1',
            'course2' => '6.2',
        ));
    }
}
