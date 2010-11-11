<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */


namespace Doctrine\DBAL;

use Doctrine\DBAL\Connection;

class SQLParserUtils
{
    static public function getPlaceholderPositions($statement, $isPositional = true)
    {   
        $match = ($isPositional) ? '?' : ':';
        if (strpos($statement, $match) === false) {
            return array();
        }
        
        $count = 1;
        $inLiteral = false; // a valid query never starts with quotes
        $stmtLen = strlen($statement);
        $paramMap = array();
        for ($i = 0; $i < $stmtLen; $i++) {
            if ($statement[$i] == $match && !$inLiteral) {
                // real positional parameter detected
                if ($isPositional) {
                    $paramMap[$count] = $i;
                } else {
                    $name = "";
                    // TODO: Something faster/better to match this than regex?
                    for ($j = $i; ($j < $stmtLen && preg_match('(([:a-zA-Z0-9]{1}))', $statement[$j])); $j++) {
                        $name .= $statement[$j];
                    }
                    $paramMap[$name][] = $i; // named parameters can be duplicated!
                    $i = $j;
                }
                ++$count;
            } else if ($statement[$i] == "'" || $statement[$i] == '"') {
                $inLiteral = ! $inLiteral; // switch state!
            }
        }

        return $paramMap;
    }
    
    /**
     * @param string $query
     * @param array $params
     * @param array $types 
     */
    static public function expandListParameters($query, $params, $types)
    {        
        $isPositional = false;
        $arrayPositions = array();
        foreach ($types AS $name => $type) {
            if ($type === Connection::PARAM_INT_ARRAY || $type == Connection::PARAM_STR_ARRAY) {
                $arrayPositions[$name] = false;
                $isPositional = (is_numeric($name));
            }
        }
        
        if (!$arrayPositions) {
            return array($query, $params, $types);
        }
        
        ksort($params);
        ksort($types);
        
        $paramPos = self::getPlaceholderPositions($query, $isPositional);
        if ($isPositional) {
            $paramOffset = 0;
            $queryOffset = 0;
            foreach ($paramPos AS $needle => $needlePos) {
                if (!isset($arrayPositions[$needle])) {
                    continue;
                }
                
                $needle += $paramOffset;
                $needlePos += $queryOffset;
                $len = count($params[$needle]);
                
                $params = array_merge(
                    array_slice($params, 0, $needle-1),
                    $params[$needle],
                    array_slice($params, $needle)
                );
                array_unshift($params, -1); // temporary to shift keys
                unset($params[0]);
                
                $types = array_merge(
                    array_slice($types, 0, $needle-1),
                    array_fill(0, $len, $types[$needle] - Connection::ARRAY_PARAM_OFFSET), // array needles are at PDO::PARAM_* + 100
                    array_slice($types, $needle)
                );
                array_unshift($types, -1);
                unset($types[0]);
                
                $expandStr = implode(", ", array_fill(0, $len, "?"));
                $query = substr($query, 0, $needlePos) . $expandStr . substr($query, $needlePos + 1);
                
                $paramOffset += ($len -1);
                $queryOffset += (strlen($expandStr) - 1);
            }
        } else {
            throw new DBALException("Array parameters are not supported for named placeholders.");
        }
        
        return array($query, $params, $types);
    }
}