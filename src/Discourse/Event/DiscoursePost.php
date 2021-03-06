<?php

declare(strict_types=1);

namespace App\Discourse\Event;

use App\Slack\Domain\TextObject;
use App\Slack\HtmlToSlackFormatter;

use function array_key_exists;
use function ltrim;
use function preg_replace;
use function sprintf;

class DiscoursePost
{
    // phpcs:disable
    private const AUTHOR_ICON = 'https://slack-imgs.com/?c=1&o1=wi16.he16&url=https%3A%2F%2Fdiscourse-meta.s3-us-west-1.amazonaws.com%2Foriginal%2F3X%2Fc%2Fb%2Fcb4bec8901221d4a646e45e1fa03db3a65e17f59.png';

    private const COLOR = '#295473';

    private const FOOTER_ICON = 'https://slack-imgs.com/?c=1&o1=wi16.he16&url=https%3A%2F%2Fdiscourse-meta.s3-us-west-1.amazonaws.com%2Foriginal%2F3X%2Fc%2Fb%2Fcb4bec8901221d4a646e45e1fa03db3a65e17f59.png';
    // phpcs:enable

    /** @var string */
    private $channel;

    /** @var string */
    private $discourseUrl;

    /** @var array */
    private $payload;

    public function __construct(string $channel, array $payload, string $discourseUrl)
    {
        $this->channel      = sprintf('#%s', ltrim($channel, '#'));
        $this->payload      = $payload;
        $this->discourseUrl = $discourseUrl;
    }

    public function isValidForSlack(): bool
    {
        if (! isset($this->payload['post'])) {
            return false;
        }

        $post = $this->payload['post'];

        if (array_key_exists('hidden', $post) && $post['hidden']) {
            return false;
        }

        if (array_key_exists('deleted_at', $post) && $post['deleted_at']) {
            return false;
        }

        // Comment to allow broadcast of edit events
        if ($post['created_at'] !== $post['updated_at']) {
            return false;
        }

        return true;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getPostUrl(): string
    {
        $post = $this->payload['post'];
        return sprintf(
            '%s/t/%s/%s/%s',
            $this->discourseUrl,
            $post['topic_slug'],
            $post['topic_id'],
            $post['id'] ?? 1
        );
    }

    public function getFallbackMessage(): string
    {
        return sprintf(
            'Discourse: Comment created for %s: %s',
            $this->payload['post']['topic_title'],
            $this->getPostUrl()
        );
    }

    public function getMessageBlocks(): array
    {
        $post = $this->payload['post'];

        return [
            $this->createContextBlock(sprintf(
                "<%s|*Comment created for %s by %s*>",
                $this->getPostUrl(),
                $post['topic_title'],
                $post['name']
            )),
            [
                'type' => 'section',
                'text' => [
                    'type' => TextObject::TYPE_MARKDOWN,
                    'text' => $this->prepareText($post['cooked']),
                ],
            ],
            $this->createFieldsBlock($post),
        ];
    }

    private function createContextBlock(string $subject): array
    {
        return [
            'type'     => 'context',
            'elements' => [
                [
                    'type'      => 'image',
                    'image_url' => self::AUTHOR_ICON,
                    'alt_text'  => 'Discourse',
                ],
                [
                    'type' => TextObject::TYPE_MARKDOWN,
                    'text' => sprintf('<%s|*Discourse*>', $this->discourseUrl),
                ],
                [
                    'type' => TextObject::TYPE_MARKDOWN,
                    'text' => $subject,
                ],
            ],
        ];
    }

    private function createFieldsBlock(array $post): array
    {
        return [
            'type'   => 'section',
            'fields' => [
                [
                    'type' => TextObject::TYPE_MARKDOWN,
                    'text' => sprintf(
                        "*In reply to*\n<%s/t/%s/%s|%s>",
                        $this->discourseUrl,
                        $post['topic_slug'],
                        $post['topic_id'],
                        $post['topic_title']
                    ),
                ],
                [
                    'type' => TextObject::TYPE_MARKDOWN,
                    'text' => sprintf(
                        "*Posted by*\n<%s/u/%s|%s>",
                        $this->discourseUrl,
                        $post['username'],
                        $post['name']
                    ),
                ],
            ],
        ];
    }

    /**
     * Normalize links to Discourse.
     *
     * For some reason, Discourse does not provide fully-qualified URLs in its
     * API payloads, only paths, so we need to find links to such resources and
     * prepend the base URL to them.
     */
    private function prepareText(string $text): string
    {
        $formatted = (new HtmlToSlackFormatter())->format($text);
        return preg_replace(
            '#<(?!https?://)([^|>]+)\|([^>]+)\>#',
            '<' . $this->discourseUrl . '\1|\2>',
            $formatted
        );
    }
}
