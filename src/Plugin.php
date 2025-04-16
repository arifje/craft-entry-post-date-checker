<?php

namespace arjanbrinkman\craftentrypostdatechecker;

use Craft;

use arjanbrinkman\craftentrypostdatechecker\models\Settings;
use arjanbrinkman\craftentrypostdatechecker\web\assets\postdatechecker\PostDateCheckerAsset;

use craft\base\Model;
use craft\base\Plugin as BasePlugin;

use craft\elements\Entry;
use craft\helpers\ElementHelper;
use craft\web\View;
use craft\helpers\App;
use craft\events\TemplateEvent;

use yii\base\Event;

use DateTime;
use DateInterval;
use yii\base\ModelEvent;

/**
 * Entry PostDate Checker plugin
 *
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                // Define component configs here...
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

		if (Craft::$app->getRequest()->getIsCpRequest()) {
			Craft::$app->getView()->registerAssetBundle(PostDateCheckerAsset::class);

			$message = Craft::$app->getSession()->getFlash('entryPostDateConflict');
			if ($message) {
				Craft::$app->getView()->registerJs("window.entryPostDateConflict = " . json_encode($message) . ";", View::POS_HEAD);
			}
		}

        if (version_compare(Craft::$app->getVersion(), '5.0', '>=')) {
			$this->attachEventHandlersCraft5();
		} else {
			$this->attachEventHandlersCraft4();
		}

        // Any code that creates an element query or loads Twig should be deferred until
        // after Craft is fully initialized, to avoid conflicts with other plugins/modules
        Craft::$app->onInit(function() {
            // ...
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('_entry-post-date-checker/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

	private function attachEventHandlersCraft4(): void
	{
		Event::on(
			Entry::class,
			Entry::EVENT_AFTER_SAVE,
			function (Event $event) {
				$entry = $event->sender;

				if (!$entry->postDate || !$entry->enabled || $entry->getIsDraft() || $entry->getIsRevision()) {
					return;
				}

				$message = $this->checkForConflicts($entry);
				if ($message) {
					Craft::$app->getSession()->setNotice($message);
				}
			}
		);
	}

	private function attachEventHandlersCraft5(): void
	{
		Event::on(
			Entry::class,
			Entry::EVENT_AFTER_SAVE,
			function (Event $event) {
				$entry = $event->sender;

				if (!$entry->postDate || !$entry->enabled || $entry->getIsDraft() || $entry->getIsRevision()) {
					return;
				}

				$message = $this->checkForConflicts($entry);
				if ($message && Craft::$app->request->getIsCpRequest()) {
					Craft::$app->getSession()->setFlash('entryPostDateConflict', $message);
				}
			}
		);
	}


	private function checkForConflicts(Entry $entry): ?string
	{
		$postDate = $entry->postDate;
		if (!$postDate) {
			return null;
		}

		$entryId = $entry->id;
		$sectionId = $entry->sectionId;
		$timeScopeMinutes = (int) $this->getSettings()->timeScopeMinutes ?: 15;
		$scopeInSeconds = $timeScopeMinutes * 60;

		$entries = Entry::find()
			->sectionId($sectionId)
			->id(['not', $entryId])
			->status(null)
			->all();

		foreach ($entries as $otherEntry) {
			if (!$otherEntry->postDate) {
				continue;
			}

			$diffInSeconds = abs($postDate->getTimestamp() - $otherEntry->postDate->getTimestamp());

			if ($diffInSeconds <= $scopeInSeconds) {
				return Craft::t('app', 'Er is al een ander artikel ingepland op {start}. <br>We raden je aan om minimaal een half uur tussen artikelen te houden, tenzij het brekend nieuws is.', [
					'start' => $otherEntry->postDate->format('H:i'),
					'date' => $postDate->format('d-m-Y'),
				]);
			}
		}

		return null; // No conflicts
	}



}
