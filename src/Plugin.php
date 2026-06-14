<?php

namespace arjanbrinkman\craftentrypostdatechecker;

use Craft;
use DateTimeInterface;
use arjanbrinkman\craftentrypostdatechecker\models\Settings;
use arjanbrinkman\craftentrypostdatechecker\web\assets\postdatechecker\PostDateCheckerAsset;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\elements\Entry;
use craft\events\ModelEvent;
use craft\helpers\Json;
use craft\web\View;
use yii\base\Event;

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

    public function init(): void
    {
        parent::init();

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->registerCpAssets();
        }

        $this->attachEventHandlers();
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

    private function registerCpAssets(): void
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(PostDateCheckerAsset::class);

        $conflict = Craft::$app->getSession()->getFlash('entryPostDateConflict');
        if ($conflict !== null) {
            $view->registerJs('window.entryPostDateConflict = ' . Json::htmlEncode($conflict) . ';', View::POS_HEAD);
        }
    }

    private function attachEventHandlers(): void
    {
        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_SAVE,
            function (ModelEvent $event): void {
                if (!Craft::$app->getRequest()->getIsCpRequest()) {
                    return;
                }

                $entry = $event->sender;
                if (!$entry instanceof Entry || !$this->shouldCheckEntry($entry)) {
                    return;
                }

                $conflict = $this->checkForConflicts($entry);
                if ($conflict !== null) {
                    Craft::$app->getSession()->setFlash('entryPostDateConflict', $conflict);
                }
            }
        );
    }

    private function shouldCheckEntry(Entry $entry): bool
    {
        return (
            $entry->postDate !== null &&
            $entry->sectionId !== null &&
            $entry->siteId !== null &&
            $entry->enabled &&
            $entry->getEnabledForSite() &&
            !$entry->getIsDraft() &&
            !$entry->getIsRevision()
        );
    }

    /**
     * @return array{title: string, message: string, recommendation: string, buttonLabel: string}|null
     */
    private function checkForConflicts(Entry $entry): ?array
    {
        $postDate = $entry->postDate;
        if (!$postDate) {
            return null;
        }

        $timeScopeMinutes = max(1, (int)$this->getSettings()->timeScopeMinutes);
        $scopeInSeconds = $timeScopeMinutes * 60;

        $startRange = clone $postDate;
        $startRange->modify("-$scopeInSeconds seconds");

        $endRange = clone $postDate;
        $endRange->modify("+$scopeInSeconds seconds");

        $conflictingEntry = Entry::find()
            ->sectionId($entry->sectionId)
            ->siteId($entry->siteId)
            ->id(['not', $entry->id])
            ->postDate([
                'and',
                sprintf('>= %s', $startRange->format(DateTimeInterface::ATOM)),
                sprintf('<= %s', $endRange->format(DateTimeInterface::ATOM)),
            ])
            ->status([Entry::STATUS_LIVE, Entry::STATUS_PENDING])
            ->drafts(false)
            ->revisions(false)
            ->one();

        if (!$conflictingEntry instanceof Entry || !$conflictingEntry->postDate) {
            return null;
        }

        return $this->createConflictPayload($conflictingEntry, $timeScopeMinutes);
    }

    /**
     * @return array{title: string, message: string, recommendation: string, buttonLabel: string}
     */
    private function createConflictPayload(Entry $conflictingEntry, int $timeScopeMinutes): array
    {
        $minimumGapMinutes = $timeScopeMinutes * 2;
        $minimumGap = $minimumGapMinutes === 1
            ? Craft::t('_entry-post-date-checker', '1 minuut')
            : Craft::t('_entry-post-date-checker', '{minutes} minuten', ['minutes' => $minimumGapMinutes]);

        return [
            'title' => Craft::t('_entry-post-date-checker', 'Waarschuwing'),
            'message' => Craft::t('_entry-post-date-checker', 'Er is al een ander artikel ingepland op {date} om {time}.', [
                'date' => $conflictingEntry->postDate->format('d-m-Y'),
                'time' => $conflictingEntry->postDate->format('H:i'),
            ]),
            'recommendation' => Craft::t('_entry-post-date-checker', 'We raden je aan om minimaal {gap} tussen artikelen te houden, tenzij het brekend nieuws is.', [
                'gap' => $minimumGap,
            ]),
            'buttonLabel' => Craft::t('_entry-post-date-checker', 'Oké, begrepen'),
        ];
    }
}
