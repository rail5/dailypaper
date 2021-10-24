# dailypaper

Automatically pulls articles from either People's World, Granma, the CPUSA, or QiuShi, produces a PDF via LaTeX, and sends that PDF to Liesel (https://gitlab.com/rail5/bookthief) to prepare it for home-printing

# Usage

See examplescript.sh

A kind of command chain that I might use myself:

`php dailypaper.php --pw --invert > pw.tex && pdflatex -interaction=nonstopmode ./pw.tex && liesel -i ./pw.pdf -vbfg -d 200 -t 8.5x11 -o ./pw-to-print.pdf`

**Liesel**  (https://gitlab.com/rail5/bookthief) is not required, but useful if you want to print the resulting PDF at home

# Requirements

- LaTeX (to run pdflatex command, should be able to handle including local images etc)

- ImageMagick (to run the --invert option)
