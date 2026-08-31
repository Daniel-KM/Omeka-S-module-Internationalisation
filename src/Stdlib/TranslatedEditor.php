<?php declare(strict_types=1);

namespace Internationalisation\Stdlib;

use Doctrine\DBAL\Connection;

/**
 * Edit the rows of the table "translated" one by one.
 *
 * The table is a simple three columns table without event, so the api is not
 * used. A row is identified by its language and its string.
 */
class TranslatedEditor
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Update the string or the translation of a row.
     *
     * @throws \RuntimeException With a message ready to be displayed.
     * @return array The row as stored after the update.
     */
    public function update(string $language, string $string, string $field, string $text): array
    {
        if (!in_array($field, ['string', 'translation'], true)) {
            throw new \RuntimeException('The field to update is unknown.'); // @translate
        }

        if ($string === '' || $text === '') {
            throw new \RuntimeException('The string and the translation cannot be empty.'); // @translate
        }

        $translation = $this->translationOf($language, $string);
        if ($translation === null) {
            throw new \RuntimeException('This string does not exist anymore: reload the page.'); // @translate
        }

        // The string is the key of the row, so it cannot become a duplicate.
        if ($field === 'string'
            && $text !== $string
            && $this->translationOf($language, $text) !== null
        ) {
            throw new \RuntimeException('This string is already translated in this language.'); // @translate
        }

        $this->connection->executeStatement(
            sprintf('UPDATE `translated` SET `%s` = :text WHERE `lang` = :lang AND `string` = :string', $field),
            ['text' => $text, 'lang' => $language, 'string' => $string]
        );

        return [
            'string' => $field === 'string' ? $text : $string,
            'translation' => $field === 'translation' ? $text : $translation,
        ];
    }

    /**
     * Delete a string and its translation.
     *
     * @throws \RuntimeException With a message ready to be displayed.
     */
    public function delete(string $language, string $string): array
    {
        if ($string === '') {
            throw new \RuntimeException('The string cannot be empty.'); // @translate
        }

        $count = $this->connection->executeStatement(
            'DELETE FROM `translated` WHERE `lang` = :lang AND `string` = :string',
            ['lang' => $language, 'string' => $string]
        );
        if (!$count) {
            throw new \RuntimeException('This string does not exist anymore: reload the page.'); // @translate
        }

        return ['string' => $string];
    }

    /**
     * Get the translation of a string, or null when the row does not exist.
     */
    public function translationOf(string $language, string $string): ?string
    {
        $result = $this->connection
            ->executeQuery(
                'SELECT `translation` FROM `translated` WHERE `lang` = :lang AND `string` = :string LIMIT 1',
                ['lang' => $language, 'string' => $string]
            )
            ->fetchOne();
        return $result === false ? null : (string) $result;
    }
}
