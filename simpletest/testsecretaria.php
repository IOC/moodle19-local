<?php

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/local/secretaria/lib.php");

Mock::generate('local_secretaria_moodle', 'mock_local_secretaria_moodle');

abstract class local_secretaria_test_base extends UnitTestCase {

    protected $moodle;
    protected $realcfg;
    protected $service;

    function setUp() {
        global $CFG;
        $this->realcfg = $CFG;
        $this->moodle = new mock_local_secretaria_moodle();
        $this->service = new local_secretaria_service($this->moodle);
    }

    function tearDown() {
        global $CFG;
        $CFG = $this->realcfg;
    }
}

/* Users */

class local_secretaria_test_get_user extends local_secretaria_test_base {

    function setUp() {
        global $CFG;

        parent::setUp();

        $CFG->wwwroot = 'http://example.org';
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
        $this->moodle->setReturnValue('get_user_record', $this->record, array('user'));
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
        $this->moodle->setReturnValue('get_user_record', false, array('user'));
        $result = $this->service->get_user('user');
        $this->assertNull($result);
    }

    function test_return_null_picture_if_user_does_not_have_one() {
        $this->record->picture = 0;
        $this->moodle->setReturnValue('get_user_record', $this->record, array('user'));
        $result = $this->service->get_user('user');
        $this->assertNull($result['picture']);
    }

}

class local_secretaria_test_create_user extends local_secretaria_test_base {

    function setUp() {
        parent::setUp();
        $this->properties = array(
            'username' => 'user',
            'firstname' => 'First',
            'lastname' => 'Last',
            'email' => 'user@example.org',
            'password' => 'abc123',
        );
    }

    function test_insert_user_record() {
        $this->moodle->setReturnValue('get_user_id', false, array('user'));
        $this->moodle->expectOnce(
            'insert_user', array('user', 'abc123', 'First', 'Last', 'user@example.org'));
        $this->moodle->setReturnValue('insert_user', true);

        $result = $this->service->create_user($this->properties);

        $this->assertTrue($result);
    }

    function test_fail_if_email_is_missing() {
        unset($this->properties['firstrname']);
        $result = $this->service->create_user($this->properties);
        $this->assertFalse($result);
    }

    function test_fail_if_firstname_is_missing() {
        unset($this->properties['firstrname']);
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
        $this->moodle->setReturnValue('get_user_id', 123, array('user'));
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
        $this->moodle->setReturnValue('get_user_id', 123);
        $this->moodle->setReturnValue('update_record', false);

        $result = $this->service->update_user('user', array());

        $this->assertFalse($result);
    }

    function test_fail_if_user_does_not_exist() {
        $this->moodle->setReturnValue('get_user_id', false);
        $result = $this->service->update_user('user', array());
        $this->assertFalse($result);
    }

    function test_fail_if_username_already_exists() {
        $this->moodle->setReturnValue('get_user_id', 123, array('user1'));
        $this->moodle->setReturnValue('get_user_id', 456, array('user2'));
        $result = $this->service->update_user('user1', array('username' => 'user2'));
        $this->assertFalse($result);
    }

    function test_update_user_record() {
        $this->moodle->setReturnValue('get_user_id', 123, array('user'));
        $this->moodle->setReturnValue('get_user_id', false, array('user2'));
        $this->moodle->expectOnce('update_record', array('user', (object) array(
            'id' => 123,
            'username' => 'user2',
            'password' => hash_internal_user_password('abc123'),
            'firstname' => 'First2',
            'lastname' => 'Last2',
            'email' => 'user2@example.org',
        )));
        $this->moodle->setReturnValue('update_record', true);

        $result = $this->service->update_user('user', array(
            'username' => 'user2',
            'password' => 'abc123',
            'firstname' => 'First2',
            'lastname' => 'Last2',
            'email' => 'user2@example.org',
        ));

        $this->assertTrue($result);
    }

    function test_update_username_if_not_changed() {
        $this->moodle->setReturnValue('get_user_id', 123, array('user'));
        $this->moodle->setReturnValue('update_record', true);
        $result = $this->service->update_user('user', array('username' => 'user'));
        $this->assertTrue($result);
    }
}

class local_secretaria_test_delete_user extends local_secretaria_test_base {

    function test_delete_user() {
        $record = (object) array('id' => 123, 'username' => 'user', '...' => '...');
        $this->moodle->setReturnValue('get_user_record', $record, array('user'));
        $this->moodle->expectOnce('delete_user', array($record));
        $this->moodle->setReturnValue('delete_user', true);

        $result = $this->service->delete_user('user');

        $this->assertTrue($result);
    }

    function test_fail_if_user_does_not_exist() {
        $this->moodle->setReturnValue('get_user_record', false, array('user'));
        $result = $this->service->delete_user('user', array());
        $this->assertFalse($result);
    }
}

/* Enrolments */

class local_secretaria_test_get_course_enrolments extends local_secretaria_test_base {

    function test_fail_if_course_does_not_exist() {
        $this->moodle->setReturnValue('get_context_id', false);
        $result = $this->service->get_course_enrolments('course1');
        $this->assertNull($result);
    }

    function test_return_empty_array_if_no_enrolments() {
        $this->moodle->setReturnValue('get_context_id', 123);
        $this->moodle->setReturnValue('get_role_assignments_by_context', array(), array(123));

        $result = $this->service->get_course_enrolments('course1');

        $this->assertEqual($result, array());
    }

    function test_return_enrolments() {
        $this->moodle->setReturnValue('get_context_id', 123, array('course1'));
        $records = array(
            (object) array('id' => 456, 'user' => 'user1', 'role' => 'role1'),
            (object) array('id' => 789, 'user' => 'user2', 'role' => 'role2'),
        );
        $this->moodle->setReturnValue('get_role_assignments_by_context', $records, array(123));

        $result = $this->service->get_course_enrolments('course1');

        $this->assertEqual($result, array(
            array('user' => 'user1', 'role' => 'role1'),
            array('user' => 'user2', 'role' => 'role2'),
        ));
    }
}

class local_secretaria_test_get_user_enrolments extends local_secretaria_test_base {

    function test_fail_if_course_does_not_exist() {
        $this->moodle->setReturnValue('get_user_id', false);
        $result = $this->service->get_user_enrolments('user');
        $this->assertNull($result);
    }

    function test_return_empty_array_if_no_enrolments() {
        $this->moodle->setReturnValue('get_user_id', 123);
        $this->moodle->setReturnValue('get_role_assignments_by_user', array());

        $result = $this->service->get_user_enrolments('user1');

        $this->assertEqual($result, array());
    }

    function test_return_enrolments() {
        $this->moodle->setReturnValue('get_user_id', 123, array('user1'));
        $records = array(
            (object) array('id' => 456, 'course' => 'course1', 'role' => 'role1'),
            (object) array('id' => 789, 'course' => 'course2', 'role' => 'role2'),
        );
        $this->moodle->setReturnValue('get_role_assignments_by_user', $records, array(123));

        $result = $this->service->get_user_enrolments('user1');

        $this->assertEqual($result, array(
            array('course' => 'course1', 'role' => 'role1'),
            array('course' => 'course2', 'role' => 'role2'),
        ));
    }
}

class local_secretaria_test_enrol_users extends local_secretaria_test_base {

    function test_ignore_existent_enrolment() {
        $this->moodle->expectOnce('start_transaction');
        $this->moodle->setReturnValue('get_context_id', 101);
        $this->moodle->setReturnValue('get_user_id', 201);
        $this->moodle->setReturnValue('get_role_id', 301);
        $this->moodle->setReturnValue('role_assignment_exists', true);
        $this->moodle->expectNever('insert_role_assignment');
        $this->moodle->expectOnce('commit_transaction');

        $result = $this->service->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));

        $this->assertTrue($result);
    }

    function test_insert_role_assignments() {
        $this->moodle->expectOnce('start_transaction');
        $this->moodle->setReturnValue('get_context_id', 101, array('course1'));
        $this->moodle->setReturnValue('get_context_id', 102, array('course2'));
        $this->moodle->setReturnValue('get_user_id', 201, array('user1'));
        $this->moodle->setReturnValue('get_user_id', 202, array('user2'));
        $this->moodle->setReturnValue('get_role_id', 301, array('role1'));
        $this->moodle->setReturnValue('get_role_id', 302, array('role2'));
        $this->moodle->setReturnValue('role_assignment_exists', false);
        $this->moodle->expectAt(0, 'insert_role_assignment', array(101, 201, 301));
        $this->moodle->expectAt(1, 'insert_role_assignment', array(102, 202, 302));
        $this->moodle->setReturnValue('insert_role_assignment', true);
        $this->moodle->expectOnce('commit_transaction');

        $result = $this->service->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
            array('course' => 'course2', 'user' => 'user2', 'role' => 'role2'),
        ));

        $this->assertTrue($result);
    }

    function test_rollback_if_context_does_not_exist() {
        $this->moodle->expectOnce('start_transaction');
        $this->moodle->setReturnValue('get_context_id', false, array('course1'));
        $this->moodle->setReturnValue('get_user_id', 201, array('user1'));
        $this->moodle->setReturnValue('get_role_id', 301, array('role1'));
        $this->moodle->expectOnce('rollback_transaction');

        $result = $this->service->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));

        $this->assertFalse($result);
    }

    function test_rollback_if_insert_fails() {
        $this->moodle->expectOnce('start_transaction');
        $this->moodle->setReturnValue('get_context_id', 101);
        $this->moodle->setReturnValue('get_user_id', 201);
        $this->moodle->setReturnValue('get_role_id', 301);
        $this->moodle->setReturnValue('role_assignment_exists', false);
        $this->moodle->setReturnValue('insert_role_assignment', false);
        $this->moodle->expectOnce('rollback_transaction');
        $this->moodle->expectNever('commit_transaction');

        $result = $this->service->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));

        $this->assertFalse($result);
    }

    function test_rollback_if_user_does_not_exist() {
        $this->moodle->expectOnce('start_transaction');
        $this->moodle->setReturnValue('get_context_id', 101);
        $this->moodle->setReturnValue('get_user_id', false);
        $this->moodle->setReturnValue('get_role_id', 301);
        $this->moodle->expectOnce('rollback_transaction');

        $result = $this->service->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));

        $this->assertFalse($result);
    }

    function test_rollback_if_role_does_not_exist() {
        $this->moodle->expectOnce('start_transaction');
        $this->moodle->setReturnValue('get_context_id', 101);
        $this->moodle->setReturnValue('get_user_id', 201);
        $this->moodle->setReturnValue('get_role_id', false);
        $this->moodle->expectOnce('rollback_transaction');

        $result = $this->service->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));

        $this->assertFalse($result);
    }
}

class local_secretaria_test_unenrol_users extends local_secretaria_test_base {

    function test_delete_role_assignments() {
        $this->moodle->expectOnce('start_transaction');
        $this->moodle->setReturnValue('get_context_id', 101, array('course1'));
        $this->moodle->setReturnValue('get_context_id', 102, array('course2'));
        $this->moodle->setReturnValue('get_user_id', 201, array('user1'));
        $this->moodle->setReturnValue('get_user_id', 202, array('user2'));
        $this->moodle->setReturnValue('get_role_id', 301, array('role1'));
        $this->moodle->setReturnValue('get_role_id', 302, array('role2'));
        $this->moodle->expectAt(0, 'delete_role_assignment', array(101, 201, 301));
        $this->moodle->expectAt(1, 'delete_role_assignment', array(102, 202, 302));
        $this->moodle->expectOnce('commit_transaction');

        $result = $this->service->unenrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
            array('course' => 'course2', 'user' => 'user2', 'role' => 'role2'),
        ));

        $this->assertTrue($result);
    }

    function test_rollback_if_context_does_not_exist() {
        $this->moodle->expectOnce('start_transaction');
        $this->moodle->setReturnValue('get_context_id', false);
        $this->moodle->setReturnValue('get_user_id', 201);
        $this->moodle->setReturnValue('get_role_id', 301);
        $this->moodle->expectOnce('rollback_transaction');

        $result = $this->service->unenrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));

        $this->assertFalse($result);
    }

    function test_rollback_user_does_not_exist() {
        $this->moodle->expectOnce('start_transaction');
        $this->moodle->setReturnValue('get_context_id', 101);
        $this->moodle->setReturnValue('get_user_id', false);
        $this->moodle->setReturnValue('get_role_id', 301);
        $this->moodle->expectOnce('rollback_transaction');

        $result = $this->service->unenrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));

        $this->assertFalse($result);
    }

    function test_rollback_if_role_does_not_exist() {
        $this->moodle->expectOnce('start_transaction');
        $this->moodle->setReturnValue('get_context_id', 101);
        $this->moodle->setReturnValue('get_user_id', 201);
        $this->moodle->setReturnValue('get_role_id', false);
        $this->moodle->expectOnce('rollback_transaction');

        $result = $this->service->unenrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));

        $this->assertFalse($result);
    }
}

/* Groups */

class local_secretaria_test_get_groups extends local_secretaria_test_base {

    function test_fail_if_course_does_not_exist() {
        $this->moodle->setReturnValue('get_course_id', false);
        $result = $this->service->get_groups('course1');
        $this->assertNull($result);
    }

    function test_return_groups() {
        $records = array(
            (object) array('id' => 201, 'name' => 'group1', 'description' => 'first group'),
            (object) array('id' => 202, 'name' => 'group2', 'description' => 'second group'),
        );
        $this->moodle->setReturnValue('get_course_id', 101, array('course1'));
        $this->moodle->setReturnValue('get_groups', $records, array(101));

        $result = $this->service->get_groups('course1');

        $this->assertEqual($result, array(
            array('name' => 'group1', 'description' => 'first group'),
            array('name' => 'group2', 'description' => 'second group'),
        ));
    }

    function test_return_empty_array_if_no_groups() {
        $this->moodle->setReturnValue('get_course_id', 101);
        $this->moodle->setReturnValue('get_groups', false);

        $result = $this->service->get_groups('course1');

        $this->assertEqual($result, array());
    }
}

class local_secretaria_test_create_group extends local_secretaria_test_base {

    function test_create_group() {
        $this->moodle->setReturnValue('get_course_id', 101, array('course1'));
        $this->moodle->setReturnValue('get_group_id', false, array(101, 'group1'));
        $this->moodle->expectOnce('insert_group', array(101, 'group1', 'first group'));
        $this->moodle->setReturnValue('insert_group', true);

        $result = $this->service->create_group('course1', 'group1', 'first group');

        $this->assertTrue($result);
    }

    function test_fail_if_course_does_not_exist() {
        $this->moodle->setReturnValue('get_course_id', false);
        $this->moodle->expectNever('insert_group');

        $result = $this->service->create_group('course1', 'group1', 'first group');

        $this->assertFalse($result);
    }

    function test_fail_if_group_exists() {
        $this->moodle->setReturnValue('get_course_id', 101);
        $this->moodle->setReturnValue('get_group_id', 201);
        $this->moodle->expectNever('insert_group');

        $result = $this->service->create_group('course1', 'group1', 'first group');

        $this->assertFalse($result);
    }

    function test_fail_if_insert_fails() {
        $this->moodle->setReturnValue('get_course_id', 101);
        $this->moodle->setReturnValue('get_group_id', 201);
        $this->moodle->setReturnValue('insert_group', false);

        $result = $this->service->create_group('course1', 'group1', 'first group');

        $this->assertFalse($result);
    }
}

class local_secretaria_test_delete_group extends local_secretaria_test_base {

    function test_fail_if_course_does_not_exist() {
        $this->moodle->setReturnValue('get_course_id', false);
        $result = $this->service->delete_group('course1', 'group1');
        $this->assertFalse($result);
    }

    function test_fail_if_group_does_not_exist() {
        $this->moodle->setReturnValue('get_course_id', 101);
        $this->moodle->setReturnValue('get_group_id', false);

        $result = $this->service->delete_group('course1', 'group1');

        $this->assertFalse($result);
    }

    function test_delete_group() {
        $this->moodle->setReturnValue('get_course_id', 101, array('course1'));
        $this->moodle->setReturnValue('get_group_id', 201, array(101, 'group1'));
        $this->moodle->expectOnce('groups_delete_group', array(201));

        $result = $this->service->delete_group('course1', 'group1');

        $this->assertTrue($result);
    }
}

class local_secretaria_test_get_group_members extends local_secretaria_test_base {

    function test_fail_if_course_does_not_exist() {
        $this->moodle->setReturnValue('get_course_id', false);
        $result = $this->service->get_group_members('course1', 'group1');
        $this->assertNull($result);
    }

    function test_fail_if_group_does_not_exist() {
        $this->moodle->setReturnValue('get_course_id', 101);
        $this->moodle->setReturnValue('get_group_id', false);
        $result = $this->service->get_group_members('course1', 'group1');
        $this->assertNull($result);
    }

    function test_return_group_members() {
        $records = array(
            (object) array('id' => 301, 'username' => 'user1'),
            (object) array('id' => 302, 'username' => 'user2'),
        );
        $this->moodle->setReturnValue('get_course_id', 101, array('course1'));
        $this->moodle->setReturnValue('get_group_id', 201, array(101, 'group1'));
        $this->moodle->setReturnValue('get_group_members', $records, array(201));

        $result = $this->service->get_group_members('course1', 'group1');

        $this->assertEqual($result, array('user1', 'user2'));
    }

    function test_return_empty_array_if_no_members() {
        $this->moodle->setReturnValue('get_course_id', 101);
        $this->moodle->setReturnValue('get_group_id', 201);
        $this->moodle->setReturnValue('get_group_members', false);

        $result = $this->service->get_group_members('course1', 'group1');

        $this->assertEqual($result, array());
    }
}

class local_secretaria_test_add_group_members extends local_secretaria_test_base {

    function test_add_group_members() {
        $this->moodle->expectOnce('start_transaction');
        $this->moodle->setReturnValue('get_course_id', 101, array('course1'));
        $this->moodle->setReturnValue('get_group_id', 201, array(101, 'group1'));
        $this->moodle->setReturnValue('get_user_id', 301, array('user1'));
        $this->moodle->setReturnValue('get_user_id', 302, array('user2'));
        $this->moodle->expectAt(0, 'groups_add_member', array(201, 301));
        $this->moodle->expectAt(1, 'groups_add_member', array(201, 302));
        $this->moodle->setReturnValue('groups_add_member', true);
        $this->moodle->expectCallCount('groups_add_member', 2);
        $this->moodle->expectOnce('commit_transaction');

        $result = $this->service->add_group_members('course1', 'group1', array('user1', 'user2'));

        $this->assertTrue($result);
    }

    function test_fail_if_course_does_not_exist() {
        $this->moodle->setReturnValue('get_course_id', false);
        $result = $this->service->add_group_members('course1', 'group1', array('user1', 'user2'));
        $this->assertFalse($result);
    }

    function test_fail_if_group_does_not_exist() {
        $this->moodle->setReturnValue('get_course_id', 101);
        $this->moodle->setReturnValue('get_group_id', false);

        $result = $this->service->add_group_members('course1', 'group1', array('user1', 'user2'));

        $this->assertFalse($result);
    }

    function test_rollback_if_user_does_not_exist() {
        $this->moodle->setReturnValue('get_course_id', 101);
        $this->moodle->setReturnValue('get_group_id', 201);
        $this->moodle->setReturnValue('get_user_id', false);
        $this->moodle->expectOnce('rollback_transaction');
        $this->moodle->expectNever('groups_add_member');

        $result = $this->service->add_group_members('course1', 'group1', array('user1'));

        $this->assertFalse($result);
    }

    function test_rollback_if_addition_fails() {
        $this->moodle->setReturnValue('get_course_id', 101);
        $this->moodle->setReturnValue('get_group_id', 201);
        $this->moodle->setReturnValue('get_user_id', 301);
        $this->moodle->expectOnce('rollback_transaction');
        $this->moodle->setReturnValue('groups_add_member', false);

        $result = $this->service->add_group_members('course1', 'group1', array('user1'));

        $this->assertFalse($result);
    }
}

/* Grades */

class local_secretaria_test_remove_group_members extends local_secretaria_test_base {

    function test_remove_group_members() {
        $this->moodle->expectOnce('start_transaction');
        $this->moodle->setReturnValue('get_course_id', 101, array('course1'));
        $this->moodle->setReturnValue('get_group_id', 201, array(101, 'group1'));
        $this->moodle->setReturnValue('get_user_id', 301, array('user1'));
        $this->moodle->setReturnValue('get_user_id', 302, array('user2'));
        $this->moodle->expectAt(0, 'groups_remove_member', array(201, 301));
        $this->moodle->expectAt(1, 'groups_remove_member', array(201, 302));
        $this->moodle->expectCallCount('groups_remove_member', 2);
        $this->moodle->expectOnce('commit_transaction');

        $result = $this->service->remove_group_members('course1', 'group1', array('user1', 'user2'));

        $this->assertTrue($result);
    }

    function test_fail_if_course_does_not_exist() {
        $this->moodle->setReturnValue('get_course_id', false);
        $result = $this->service->remove_group_members('course1', 'group1', array('user1', 'user2'));
        $this->assertFalse($result);
    }

    function test_fail_if_group_does_not_exist() {
        $this->moodle->setReturnValue('get_course_id', 101);
        $this->moodle->setReturnValue('get_group_id', false);

        $result = $this->service->remove_group_members('course1', 'group1', array('user1', 'user2'));

        $this->assertFalse($result);
    }

    function test_rollback_if_user_does_not_exist() {
        $this->moodle->setReturnValue('get_course_id', 101);
        $this->moodle->setReturnValue('get_group_id', 201);
        $this->moodle->setReturnValue('get_user_id', false);
        $this->moodle->expectOnce('rollback_transaction');

        $result = $this->service->remove_group_members('course1', 'group1', array('user1'));

        $this->assertFalse($result);
    }

}

class local_secretaria_test_get_course_grades extends local_secretaria_test_base {

    function test_return_null_if_course_does_not_exist() {
        $this->moodle->setReturnValue('get_course_id', false);
        $result = $this->service->get_course_grades('course1', array('user1', 'user2'));
        $this->assertNull($result);
    }

    function test_return_null_if_no_grade_items() {
        $this->moodle->setReturnValue('get_course_id', 101);
        $this->moodle->setReturnValue('get_user_id', 201);
        $this->moodle->setReturnValue('grade_item_fetch_all', false);

        $result = $this->service->get_course_grades('course1', array('user1'));

        $this->assertNull($result);
    }

    function test_return_null_if_user_does_not_exist() {
        $this->moodle->setReturnValue('get_course_id', 101);
        $this->moodle->setReturnValue('get_user_id', false);

        $result = $this->service->get_course_grades('course1', array('user1', 'user2'));

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

        $this->moodle->setReturnValue('get_course_id', 101, array('course1'));
        $this->moodle->setReturnValue('get_user_id', 201, array('user1'));
        $this->moodle->setReturnValue('get_user_id', 202, array('user2'));
        $this->moodle->setReturnValue('grade_item_fetch_all', $grade_items, array(101));
        $this->moodle->setReturnValue('grade_get_grades', $grades_course,
                                      array(101, 'course', null, 101, array(201, 202)));
        $this->moodle->setReturnValue('grade_get_grades', $grades_category,
                                      array(101, 'category', null, 301, array(201, 202)));
        $this->moodle->setReturnValue('grade_get_grades', $grades_module,
                                      array(101, 'module', 'assignment', 401, array(201, 202)));

        $result = $this->service->get_course_grades('course1', array('user1', 'user2'));

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
        $this->moodle->setReturnValue('get_user_id', 201);
        $this->moodle->setReturnValue('get_course_id', false);

        $result = $this->service->get_user_grades('user1', array('course1', 'course2'));

        $this->assertNull($result);
    }

    function test_return_null_if_no_grade() {
        $this->moodle->setReturnValue('get_user_id', 201);
        $this->moodle->setReturnValue('get_course_id', 101);
        $this->moodle->setReturnValue('grade_get_course_grade', false);

        $result = $this->service->get_user_grades('user1', array('course1'));

        $this->assertNull($result);
    }

    function test_return_null_if_user_does_not_exist() {
        $this->moodle->setReturnValue('get_user_id', false);
        $result = $this->service->get_user_grades('user1', array());
        $this->assertNull($result);
    }

    function test_return_user_grades() {
        $grade1 = (object) array('str_grade' => '5.1');
        $grade2 = (object) array('str_grade' => '6.2');

        $this->moodle->setReturnValue('get_course_id', 101, array('course1'));
        $this->moodle->setReturnValue('get_course_id', 102, array('course2'));
        $this->moodle->setReturnValue('get_user_id', 201, array('user1'));
        $this->moodle->setReturnValue('grade_get_course_grade', $grade1, array(201, 101));
        $this->moodle->setReturnValue('grade_get_course_grade', $grade2, array(201, 102));

        $result = $this->service->get_user_grades('user1', array('course1', 'course2'));

        $this->assertEqual($result, array('course1' => '5.1', 'course2' => '6.2'));
    }

}
