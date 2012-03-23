<?php

require_once 'operations.php';
require_once 'Mockery/Loader.php';

$loader = new Mockery\Loader;
$loader->register();

Mockery::getConfiguration()->allowMockingNonExistentMethods(false);

abstract class OperationTest extends PHPUnit_Framework_TestCase {

    protected $moodle;
    protected $operations;

    function setUp() {
        $this->moodle = Mockery::mock('local_secretaria_moodle');
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

    protected function having_course_id($shortname, $courseid) {
        $this->moodle->shouldReceive('get_course_id')
            ->with($shortname)->andReturn($courseid);
    }

    protected function having_group_id($courseid, $groupname, $groupid) {
        $this->moodle->shouldReceive('get_group_id')
            ->with($courseid, $groupname)->andReturn($groupid);
    }

    protected function having_mnet_localhost_id($id) {
        $this->moodle->shouldReceive('mnet_localhost_id')->andReturn($id);
    }

    protected function having_mnet_host_id($id) {
        $this->moodle->shouldReceive('mnet_host_id')->andReturn($id);
    }

    protected function having_role_id($shortname, $roleid) {
        $this->moodle->shouldReceive('get_role_id')
            ->with($shortname)->andReturn($roleid);
    }

    protected function having_user_id($mnethostid, $username, $userid) {
        $this->moodle->shouldReceive('get_user_id')
            ->with($mnethostid, $username)->andReturn($userid);
    }

    protected function having_user_record($mnethostid, $username, $record) {
        $this->moodle->shouldReceive('get_user_record')
            ->with($mnethostid, $username)->andReturn($record);
    }
}

/* Users */

class GetUserTest extends OperationTest {

    function setUp() {
        parent::setUp();
        $this->record = (object) array(
            'id' => 201,
            'username' => 'user',
            'firstname' => 'First',
            'lastname' => 'Last',
            'email' => 'user@example.org',
            'picture' => 1,
        );
        $this->having_mnet_host_id(101);
    }

    function test() {
        $this->having_user_record(101, 'user', $this->record);
        $this->moodle->shouldReceive('user_picture_url')->with(201)
            ->andReturn('http://example.org/user/pix.php/201/f1.jpg');

        $result = $this->operations->get_user('user');

        $this->assertThat($result, $this->equalTo(array(
            'username' => 'user',
            'firstname' => 'First',
            'lastname' => 'Last',
            'email' => 'user@example.org',
            'picture' => 'http://example.org/user/pix.php/201/f1.jpg',
        )));
    }

    function test_no_picture() {
        $this->record->picture = 0;
        $this->having_user_record(101, 'user', $this->record);
        $this->moodle->shouldReceive('user_picture_url')->with(201)
            ->andReturn('http://example.org/user/pix.php/201/f1.jpg');

        $result = $this->operations->get_user('user');

        $this->assertThat($result['picture'], $this->equalTo(''));
    }

    function test_unknown_user() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');
        $this->operations->get_user('user');
    }
}

class CreateUserTest extends OperationTest {

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

    function test_local() {
        $this->having_mnet_localhost_id(101);
        $this->having_mnet_host_id(101);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('check_password')
            ->with('abc123')->andReturn(true);
        $this->moodle->shouldReceive('create_user')
            ->with('manual', 101, 'user1', 'abc123', 'First', 'Last', 'user1@example.org')
            ->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->create_user($this->properties);
    }

    function test_remote() {
        $this->having_mnet_localhost_id(101);
        $this->having_mnet_host_id(102);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('create_user')
            ->with('mnet', 102, 'user1', null, 'First', 'Last', 'user1@example.org')
            ->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->create_user($this->properties);
    }

    function test_blank_username() {
        $this->properties['username'] = '';
        $this->having_mnet_localhost_id(101);
        $this->having_mnet_host_id(101);
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->create_user($this->properties);
    }

    function test_blank_firstname() {
        $this->properties['firstname'] = '';
        $this->having_mnet_localhost_id(101);
        $this->having_mnet_host_id(101);
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->create_user($this->properties);
    }

    function test_blank_lastname() {
        $this->properties['lastname'] = '';
        $this->having_mnet_localhost_id(101);
        $this->having_mnet_host_id(101);
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->create_user($this->properties);
    }

    function test_duplicate_username() {
        $this->having_mnet_localhost_id(101);
        $this->having_mnet_host_id(101);
        $this->having_user_id(101, 'user1', 201);
        $this->setExpectedException('local_secretaria_exception', 'Duplicate username');

        $this->operations->create_user($this->properties);
    }

    function test_invalid_password() {
        $this->having_mnet_localhost_id(101);
        $this->having_mnet_host_id(101);
        $this->moodle->shouldReceive('check_password')
            ->with('abc123')->andReturn(false);
        $this->setExpectedException('local_secretaria_exception', 'Invalid password');

        $this->operations->create_user($this->properties);
    }
}

class UpdateUserTest extends OperationTest {

    function test() {
        $record = (object) array(
            'id' => 201,
            'username' => 'user2',
            'firstname' => 'First2',
            'lastname' => 'Last2',
            'email' => 'user2@example.org',
        );
        $this->having_mnet_host_id(101);
        $this->having_mnet_localhost_id(101);
        $this->having_user_id(101, 'user1', 201);
        $this->having_user_id(101, 'user2', false);
        $this->moodle->shouldReceive('check_password')
            ->with('abc123')->andReturn(true);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('update_record')
            ->with('user', Mockery::mustBe($record))->once()->ordered();
        $this->moodle->shouldReceive('update_password')
            ->with(201, 'abc123')->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->update_user('user1', array(
            'username' => 'user2',
            'password' => 'abc123',
            'firstname' => 'First2',
            'lastname' => 'Last2',
            'email' => 'user2@example.org',
        ));
    }

    function test_unknown_user() {
        $this->having_mnet_host_id(101);
        $this->having_mnet_localhost_id(101);
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');

        $this->operations->update_user('user1', array('username' => 'user1'));
    }

    function test_blank_username() {
        $this->having_mnet_host_id(101);
        $this->having_mnet_localhost_id(101);
        $this->having_user_id(101, 'user1', 201);
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->update_user('user1', array('username' => ''));
    }

    function test_duplicate_username() {
        $this->having_mnet_host_id(101);
        $this->having_mnet_localhost_id(101);
        $this->having_user_id(101, 'user1', 201);
        $this->having_user_id(101, 'user2', 202);
        $this->setExpectedException('local_secretaria_exception', 'Duplicate username');

        $this->operations->update_user('user1', array('username' => 'user2'));
    }

    function test_same_username() {
        $this->having_mnet_host_id(101);
        $this->having_mnet_localhost_id(101);
        $this->having_user_id(101, 'user1', 201);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->update_user('user1', array('username' => 'user1'));
    }

    function test_password_only() {
        $this->having_mnet_host_id(101);
        $this->having_mnet_localhost_id(101);
        $this->having_user_id(101, 'user1', 201);
        $this->moodle->shouldReceive('check_password')
            ->with('abc123')->andReturn(true);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('update_password')
            ->with(201, 'abc123')->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->update_user('user1', array('password' => 'abc123'));
    }

    function test_invalid_password() {
        $this->having_mnet_host_id(101);
        $this->having_mnet_localhost_id(101);
        $this->having_user_id(101, 'user1', 201);
        $this->moodle->shouldReceive('check_password')
            ->with('abc123')->andReturn(false);
        $this->setExpectedException('local_secretaria_exception', 'Invalid password');

        $this->operations->update_user('user1', array('password' => 'abc123'));
    }

    function test_password_remote() {
        $this->having_mnet_host_id(102);
        $this->having_mnet_localhost_id(101);
        $this->having_user_id(102, 'user1', 201);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->update_user('user1', array('password' => 'abc123'));
    }

    function test_blank_firstname() {
        $this->having_mnet_host_id(101);
        $this->having_mnet_localhost_id(101);
        $this->having_user_id(101, 'user1', 201);
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->update_user('user1', array('firstname' => ''));
    }

    function test_blank_lastname() {
        $this->having_mnet_host_id(101);
        $this->having_mnet_localhost_id(101);
        $this->having_user_id(101, 'user1', 201);
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->update_user('user1', array('lastname' => ''));
    }

    function test_blank_email() {
        $record = (object) array('id' => 201, 'email' => '');
        $this->having_mnet_host_id(101);
        $this->having_mnet_localhost_id(101);
        $this->having_user_id(101, 'user1', 201);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('update_record')
            ->with('user', Mockery::mustBe($record))->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->update_user('user1', array('email' => ''));
    }
}

class DeleteUserTest extends OperationTest {

    function test() {
        $record = (object) array(
            'id' => 201,
            'username' => 'user1',
            'password' => 'abc123',
            'firstname' => 'First2',
            'lastname' => 'Last2',
            'email' => 'user2@example.org',
        );
        $this->having_mnet_host_id(101);
        $this->having_user_record(101, 'user1', $record);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('delete_user')
            ->with(Mockery::mustBe($record))
            ->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->delete_user('user1');
    }

    function test_unknown_user() {
        $this->having_mnet_host_id(101);
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');

        $this->operations->delete_user('user1');
    }
}

/* Enrolments */

class GetCcourseEnrolmentsTest extends OperationTest {

    function test() {
        $records = array(
            (object) array('id' => 301, 'user' => 'user1', 'role' => 'role1'),
            (object) array('id' => 302, 'user' => 'user2', 'role' => 'role2'),
        );

        $this->having_course_id('course1', 101);
        $this->having_mnet_host_id(201);
        $this->moodle->shouldReceive('get_role_assignments_by_course')
            ->with(101, 201)->andReturn($records);

        $result = $this->operations->get_course_enrolments('course1');

        $this->assertThat($result, $this->equalTo(array(
            array('user' => 'user1', 'role' => 'role1'),
            array('user' => 'user2', 'role' => 'role2'),
        )));
    }

    function test_no_enrolments() {
        $this->having_course_id('course1', 101);
        $this->having_mnet_host_id(201);
        $this->moodle->shouldReceive('get_role_assignments_by_course')
            ->with(101, 201)->andReturn(array());

        $result = $this->operations->get_course_enrolments('course1');

        $this->assertThat($result, $this->equalTo(array()));
    }

    function test_unknown_course() {
        $this->having_mnet_host_id(201);
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_course_enrolments('course1');
    }
}

class GetUserEnrolmentsTest extends OperationTest {

    function test() {
        $records = array(
            (object) array('id' => 301, 'course' => 'course1', 'role' => 'role1'),
            (object) array('id' => 302, 'course' => 'course2', 'role' => 'role2'),
        );
        $this->having_mnet_host_id(101);
        $this->having_user_id(101, 'user1', 201);
        $this->moodle->shouldReceive('get_role_assignments_by_user')
            ->with(201)->andReturn($records);

        $result = $this->operations->get_user_enrolments('user1');

        $this->assertThat($result, $this->equalTo(array(
            array('course' => 'course1', 'role' => 'role1'),
            array('course' => 'course2', 'role' => 'role2'),
        )));
    }

    function test_no_enrolments() {
        $this->having_mnet_host_id(101);
        $this->having_user_id(101, 'user1', 201);
        $this->moodle->shouldReceive('get_role_assignments_by_user')
            ->with(201)->andReturn(array());

        $result = $this->operations->get_user_enrolments('user1');

        $this->assertThat($result, $this->equalTo(array()));
    }

    function test_unknown_user() {
        $this->having_mnet_host_id(101);
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');
        $this->operations->get_user_enrolments('user1');
    }
}

class EnrolUsersTest extends OperationTest {

    function test() {
        $this->having_mnet_host_id(101);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        for ($i = 1; $i <= 3; $i++) {
            $this->having_course_id('course' . $i, 200 + $i);
            $this->having_user_id(101, 'user' . $i, 300 + $i);
            $this->having_role_id('role' . $i, 400 + $i);
            $this->moodle->shouldReceive('role_assignment_exists')
                ->with(200 + $i, 300 + $i, 400 + $i)->andReturn(false);
            $this->moodle->shouldReceive('insert_role_assignment')
                ->with(200 + $i, 300 + $i, 400 + $i)->once()->ordered();
        }
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
            array('course' => 'course2', 'user' => 'user2', 'role' => 'role2'),
            array('course' => 'course3', 'user' => 'user3', 'role' => 'role3'),
        ));
    }

    function test_duplicate_enrolment() {
        $this->having_mnet_host_id(101);
        $this->having_course_id('course1', 201);
        $this->having_user_id(101, 'user1', 301);
        $this->having_role_id('role1', 401);
        $this->moodle->shouldReceive('role_assignment_exists')
                ->with(201, 301, 401)->andReturn(true);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }

    function test_unknown_course() {
        $this->having_mnet_host_id(101);
        $this->having_user_id(101, 'user1', 301);
        $this->having_role_id('role1', 401);
        $this->moodle->shouldReceive('start_transaction')->once();

        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }

    function test_unknown_user() {
        $this->having_mnet_host_id(101);
        $this->having_course_id('course1', 201);
        $this->having_role_id('role1', 401);
        $this->moodle->shouldReceive('start_transaction')->once();

        $this->setExpectedException('local_secretaria_exception', 'Unknown user');
        $this->operations->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }

    function test_unknown_role() {
        $this->having_mnet_host_id(101);
        $this->having_course_id('course1', 201);
        $this->having_user_id(101, 'user1', 301);
        $this->moodle->shouldReceive('start_transaction')->once();

        $this->setExpectedException('local_secretaria_exception', 'Unknown role');
        $this->operations->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }
}

class UnenrolUsersTest extends OperationTest {

    function test() {
        $this->having_mnet_host_id(101);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        for ($i = 1; $i <= 3; $i++) {
            $this->having_course_id('course' . $i, 200 + $i);
            $this->having_user_id(101, 'user' . $i, 300 + $i);
            $this->having_role_id('role' . $i, 400 + $i);
            $this->moodle->shouldReceive('role_assignment_exists')
                ->with(200 + $i, 300 + $i, 400 + $i)->andReturn(false);
            $this->moodle->shouldReceive('delete_role_assignment')
                ->with(200 + $i, 300 + $i, 400 + $i)->once()->ordered();
        }
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->unenrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
            array('course' => 'course2', 'user' => 'user2', 'role' => 'role2'),
            array('course' => 'course3', 'user' => 'user3', 'role' => 'role3'),
        ));
    }

    function test_unknown_course() {
        $this->having_mnet_host_id(101);
        $this->having_user_id(101, 'user1', 301);
        $this->having_role_id('role1', 401);
        $this->moodle->shouldReceive('start_transaction')->once();

        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->unenrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
       ));
    }

    function test_unknown_user() {
        $this->having_mnet_host_id(101);
        $this->having_course_id('course1', 201);
        $this->having_role_id('role1', 401);
        $this->moodle->shouldReceive('start_transaction')->once();

        $this->setExpectedException('local_secretaria_exception', 'Unknown user');
        $this->operations->unenrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }

    function test_unknown_role() {
        $this->having_mnet_host_id(101);
        $this->having_course_id('course1', 201);
        $this->having_user_id(101, 'user1', 301);
        $this->moodle->shouldReceive('start_transaction')->once();

        $this->setExpectedException('local_secretaria_exception', 'Unknown role');
        $this->operations->unenrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }
}

/* Groups */

class GetGroupsTest extends OperationTest {

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

        $this->assertThat($result, $this->equalTo(array(
            array('name' => 'group1', 'description' => 'first group'),
            array('name' => 'group2', 'description' => 'second group'),
        )));
    }

    function test_no_groups() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_groups')
            ->with(101)->andReturn(false);

        $result = $this->operations->get_groups('course1');

        $this->assertThat($result, $this->equalTo(array()));
    }

    function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_groups('course1');
    }
}

class CreateGroupTest extends OperationTest {

    function test() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('groups_create_group')
            ->with(101, 'group1', 'Group 1')
            ->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->create_group('course1', 'group1', 'Group 1');
    }

    function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->create_group('course1', 'group1', 'Group 1');
    }

    function test_blank_name() {
        $this->having_course_id('course1', 101);
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->create_group('course1', '', 'Group 1');
    }

    function test_duplicate_group() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);

        $this->setExpectedException('local_secretaria_exception', 'Duplicate group');
        $this->operations->create_group('course1', 'group1', 'Group 1');
    }
}

class DeleteGroupTest extends OperationTest {

    function test() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('groups_delete_group')->with(201)
            ->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->delete_group('course1', 'group1');
    }

    function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->delete_group('course1', 'group1');
    }

    function test_unknown_group() {
        $this->having_course_id('course1', 101);
        $this->setExpectedException('local_secretaria_exception', 'Unknown group');
        $this->operations->delete_group('course1', 'group1');
    }
}

class GetGroupMembersTest extends OperationTest {

    function test() {
        $records = array(
            (object) array('id' => 401, 'username' => 'user1'),
            (object) array('id' => 402, 'username' => 'user2'),
        );
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->having_mnet_host_id(301);
        $this->moodle->shouldReceive('get_group_members')
            ->with(201, 301)->andReturn($records);

        $result = $this->operations->get_group_members('course1', 'group1');

        $this->assertThat($result, $this->equalTo(array('user1', 'user2')));
    }

    function test_no_members() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->having_mnet_host_id(301);
        $this->moodle->shouldReceive('get_group_members')
            ->with(201, 301)->andReturn(false);

        $result = $this->operations->get_group_members('course1', 'group1');

        $this->assertThat($result, $this->equalTo(array()));
    }

    function test_unknown_course() {
        $this->having_mnet_host_id(301);
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_group_members('course1', 'group1');
    }

    function test_unknown_group() {
        $this->having_course_id('course1', 101);
        $this->having_mnet_host_id(301);
        $this->setExpectedException('local_secretaria_exception', 'Unknown group');
        $this->operations->get_group_members('course1', 'group1');
    }
}

class AddGroupMembersTest extends OperationTest {

    function test() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->having_mnet_host_id(301);
        $this->having_user_id(301, 'user1', 401);
        $this->having_user_id(301, 'user2', 402);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('groups_add_member')
            ->with(201, 401)->once()->ordered();
        $this->moodle->shouldReceive('groups_add_member')
            ->with(201, 402)->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->add_group_members('course1', 'group1', array('user1', 'user2'));
    }

    function test_unknown_course() {
        $this->having_mnet_host_id(301);
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->add_group_members('course1', 'group1', array());
    }

    function test_unknown_group() {
        $this->having_course_id('course1', 101);
        $this->having_mnet_host_id(301);
        $this->setExpectedException('local_secretaria_exception', 'Unknown group');
        $this->operations->add_group_members('course1', 'group1', array());
    }

    function test_unknown_user() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->having_mnet_host_id(301);
        $this->moodle->shouldReceive('start_transaction')->once();

        $this->setExpectedException('local_secretaria_exception', 'Unknown user');
        $this->operations->add_group_members('course1', 'group1', array('user1'));
    }
}

class RemoveGroupMembersTest extends OperationTest {

    function test() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->having_mnet_host_id(301);
        $this->having_user_id(301, 'user1', 401);
        $this->having_user_id(301, 'user2', 402);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('groups_remove_member')
            ->with(201, 401)->once()->ordered();
        $this->moodle->shouldReceive('groups_remove_member')
            ->with(201, 402)->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $result = $this->operations->remove_group_members(
            'course1', 'group1', array('user1', 'user2'));
    }

    function test_unknown_course() {
        $this->having_mnet_host_id(301);
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->remove_group_members('course1', 'group1', array());
    }

    function test_unknown_group() {
        $this->having_course_id('course1', 101);
        $this->having_mnet_host_id(301);
        $this->setExpectedException('local_secretaria_exception', 'Unknown group');
        $this->operations->remove_group_members('course1', 'group1', array());
    }

    function test_unknown_user() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->having_mnet_host_id(301);
        $this->moodle->shouldReceive('start_transaction')->once();
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');
        $this->operations->remove_group_members('course1', 'group1', array('user1'));
    }
}

/* Grades */

class GetCourseGradesTest extends OperationTest {

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
                'iteminstance' => 401,
                'itemname' => 'Category 1',
                'idnumber' => 'id301',
            ),
            (object) array(
                'itemtype' => 'module',
                'itemmodule' => 'assignment',
                'iteminstance' => 501,
                'itemname' => 'Assignment 1',
                'idnumber' => 'id401',
            ),
        );
        $grades_course = array(
            301 => (object) array('str_grade' => '5.1'),
            302 => (object) array('str_grade' => '5.2'),
        );
        $grades_category = array(
            301 => (object) array('str_grade' => '6.1'),
            302 => (object) array('str_grade' => '6.2'),
        );
        $grades_module = array(
            301 => (object) array('str_grade' => '7.1'),
            302 => (object) array('str_grade' => '7.2'),
        );

        $this->having_course_id('course1', 101);
        $this->having_mnet_host_id(201);
        $this->having_user_id(201, 'user1', 301);
        $this->having_user_id(201, 'user2', 302);
        $this->moodle->shouldReceive('grade_item_fetch_all')
            ->with(101)->andReturn($grade_items);
        $this->moodle->shouldReceive('grade_get_grades')
            ->with(101, 'course', null, 101, array(301, 302))
            ->andReturn($grades_course);
        $this->moodle->shouldReceive('grade_get_grades')
            ->with(101, 'category', null, 401, array(301, 302))
            ->andReturn($grades_category);
        $this->moodle->shouldReceive('grade_get_grades')
            ->with(101, 'module', 'assignment', 501, array(301, 302))
            ->andReturn($grades_module);

        $result = $this->operations->get_course_grades(
            'course1', array('user1', 'user2'));

        $this->assertThat($result, $this->equalTo(array(
            array('type' => 'course',
                  'module' => null,
                  'idnumber' => 'id101',
                  'name' => null,
                  'grades' => array(
                      array('user' => 'user1', 'grade' => '5.1'),
                      array('user' => 'user2', 'grade' => '5.2'),
                  )),
            array('type' => 'category',
                  'module' => null,
                  'idnumber' => 'id301',
                  'name' => 'Category 1',
                  'grades' => array(
                      array('user' => 'user1', 'grade' => '6.1'),
                      array('user' => 'user2', 'grade' => '6.2'),
                  )),
            array('type' => 'module',
                  'module' => 'assignment',
                  'idnumber' => 'id401',
                  'name' => 'Assignment 1',
                  'grades' => array(
                      array('user' => 'user1', 'grade' => '7.1'),
                      array('user' => 'user2', 'grade' => '7.2'),
                  )),
        )));
    }

    function test_no_grade_items() {
        $this->having_course_id('course1', 101);
        $this->having_mnet_host_id(201);
        $this->having_user_id(201, 'user1', 301);
        $this->having_user_id(201, 'user2', 302);
        $this->moodle->shouldReceive('grade_item_fetch_all')
            ->with(101)->andReturn(false);

        $result = $this->operations->get_course_grades(
            'course1', array('user1', 'user2'));

        $this->assertThat($result, $this->equalTo(array()));
    }

    function test_unknown_course() {
        $this->having_mnet_host_id(201);
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_course_grades('course1', array('user1', 'user2'));
    }

    function test_unknown_user() {
        $this->having_course_id('course1', 101);
        $this->having_mnet_host_id(201);
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');
        $this->operations->get_course_grades('course1', array('user1', 'user2'));
    }
}

class GetUserGradesTest extends OperationTest {

    function test() {
        $grade1 = (object) array('str_grade' => '5.1');
        $grade2 = (object) array('str_grade' => '6.2');

        $this->having_mnet_host_id(101);
        $this->having_user_id(101, 'user1', 201);
        $this->having_course_id('course1', 301);
        $this->having_course_id('course2', 302);
        $this->moodle->shouldReceive('grade_get_course_grade')
            ->with(201, 301)->andReturn($grade1);
        $this->moodle->shouldReceive('grade_get_course_grade')
            ->with(201, 302)->andReturn($grade2);

        $result = $this->operations->get_user_grades(
            'user1', array('course1', 'course2'));

        $this->assertThat($result, $this->equalTo(array(
            array('course' => 'course1', 'grade' => '5.1'),
            array('course' => 'course2', 'grade' => '6.2'),
        )));
    }

    function test_no_grade() {
        $this->having_mnet_host_id(101);
        $this->having_user_id(101, 'user1', 201);
        $this->having_course_id('course1', 301);
        $this->moodle->shouldReceive('grade_get_course_grade')
            ->with(201, 301)->andReturn(false);

        $result = $this->operations->get_user_grades('user1', array('course1'));

        $this->assertThat($result, $this->equalTo(array(
            array('course' => 'course1', 'grade' => null),
        )));
    }

    function test_unknown_course() {
        $this->having_mnet_host_id(101);
        $this->having_user_id(101, 'user1', 201);
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_user_grades('user1', array('course1', 'course2'));
    }

    function test_unknown_user() {
        $this->having_mnet_host_id(101);
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');
        $this->operations->get_user_grades('user1', array());
    }
}
