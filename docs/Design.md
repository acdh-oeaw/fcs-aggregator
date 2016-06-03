# Design decisions so far

## Show all errors so they can be corrected

Earlier versions of the script ate up errors and warnings. Especially warnings about invalid XML
were just logged and forgotten. Now every warning and every error in PHP triggers an exception
which can be caught and acted upon. There may still be paths where this is not done properly.
Now the result may just be a SRU comliant diagnostics message instead of some actual result.
