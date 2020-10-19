<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Service;

use OCA\Talk\Events\AddParticipantsEvent;
use OCA\Talk\Events\JoinRoomUserEvent;
use OCA\Talk\Events\ModifyParticipantEvent;
use OCA\Talk\Exceptions\InvalidPasswordException;
use OCA\Talk\Exceptions\UnauthorizedException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Model\SessionMapper;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Comments\IComment;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUser;

class ParticipantService {
	/** @var AttendeeMapper */
	protected $attendeeMapper;
	/** @var SessionMapper */
	protected $sessionMapper;
	/** @var SessionService */
	protected $sessionService;
	/** @var IEventDispatcher */
	private $dispatcher;

	public function __construct(AttendeeMapper $attendeeMapper,
								SessionMapper $sessionMapper,
								SessionService $sessionService,
								IEventDispatcher $dispatcher) {
		$this->attendeeMapper = $attendeeMapper;
		$this->sessionMapper = $sessionMapper;
		$this->sessionService = $sessionService;
		$this->dispatcher = $dispatcher;
	}

	/**
	 * @param Room $room
	 * @param IUser $user
	 * @param string $password
	 * @param bool $passedPasswordProtection
	 * @return Participant
	 * @throws InvalidPasswordException
	 * @throws UnauthorizedException
	 */
	public function joinRoom(Room $room, IUser $user, string $password, bool $passedPasswordProtection = false): Participant {
		$event = new JoinRoomUserEvent($room, $user, $password, $passedPasswordProtection);
		$this->dispatcher->dispatch(Room::EVENT_BEFORE_ROOM_CONNECT, $event);

		if ($event->getCancelJoin() === true) {
			// FIXME $this->removeUser($user, self::PARTICIPANT_LEFT);
			throw new UnauthorizedException('Participant is not allowed to join');
		}

		try {
			$attendee = $this->attendeeMapper->findByActor($room->getId(), 'users', $user->getUID());
		} catch (DoesNotExistException $e) {
			if (!$event->getPassedPasswordProtection() && !$room->verifyPassword($password)['result']) {
				throw new InvalidPasswordException('Provided password is invalid');
			}

			// User joining a public room, without being invited
			$this->addUsers($room, [
				'userId' => $user->getUID(),
				'participantType' => Participant::USER_SELF_JOINED,
			]);

			$attendee = $this->attendeeMapper->findByActor($room->getId(), 'users', $user->getUID());
		}

		$session = $this->sessionService->createSessionForAttendee($attendee);

		$this->dispatcher->dispatch(Room::EVENT_AFTER_ROOM_CONNECT, $event);

		return new Participant(
			\OC::$server->getDatabaseConnection(), // FIXME
			\OC::$server->getConfig(), // FIXME
			$room, $attendee, $session);
	}

	/**
	 * @param Room $room
	 * @param array ...$participants
	 */
	public function addUsers(Room $room, array ...$participants): void {
		$event = new AddParticipantsEvent($room, $participants);
		$this->dispatcher->dispatch(Room::EVENT_BEFORE_USERS_ADD, $event);

		$lastMessage = 0;
		if ($room->getLastMessage() instanceof IComment) {
			$lastMessage = (int) $room->getLastMessage()->getId();
		}

		foreach ($participants as $participant) {
			$attendee = new Attendee();
			$attendee->setRoomId($room->getId());
			$attendee->setActorType('users');
			$attendee->setActorId($participant['userId']);
			$attendee->setParticipantType($participant['participantType'] ?? Participant::USER);
			$attendee->setLastReadMessage($lastMessage);
			$this->attendeeMapper->insert($attendee);
		}

		$this->dispatcher->dispatch(Room::EVENT_AFTER_USERS_ADD, $event);
	}
}
