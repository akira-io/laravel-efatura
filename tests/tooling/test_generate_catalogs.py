"""Regression checks for the build-time official catalog generator."""

from __future__ import annotations

import json
import sys
import unittest
from contextlib import redirect_stdout
from io import StringIO
from pathlib import Path
from tempfile import TemporaryDirectory
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "scripts"))
import generate_catalogs as generator


class CatalogGeneratorTest(unittest.TestCase):
    def test_rejects_exemption_source_disagreement(self) -> None:
        with (
            patch.object(generator, "units", return_value=([], {"sources": []})),
            patch.object(
                generator, "places", return_value=([], [], Path("places.xlsx"))
            ),
            patch.object(
                generator,
                "tax_exemptions",
                return_value=([{"code": "OTHER"}], Path("exemptions.xlsx")),
            ),
            patch.object(generator, "xsd_rows", return_value=[{"code": "XSD"}]),
            patch.object(
                generator,
                "source",
                return_value={"path": "fixture", "sha256": "fixture"},
            ),
        ):
            with self.assertRaisesRegex(ValueError, "Tax exemption.*source"):
                generator.build()

    def test_rejects_duplicate_catalog_codes(self) -> None:
        payload = {"units": {"records": [{"code": "C62"}, {"code": "C62"}]}}
        with TemporaryDirectory() as directory:
            (Path(directory) / "catalogs").mkdir()
            with (
                patch.object(generator, "RESOURCES", Path(directory)),
                patch.object(generator, "build", return_value=payload),
                patch.object(sys, "argv", ["generate_catalogs.py"]),
                redirect_stdout(StringIO()),
            ):
                with self.assertRaisesRegex(ValueError, "Duplicate codes in units"):
                    generator.main()

    def test_check_rejects_stale_catalog_bytes(self) -> None:
        with TemporaryDirectory() as directory:
            catalog_directory = Path(directory) / "catalogs"
            catalog_directory.mkdir()
            (catalog_directory / "units.json").write_bytes(b"stale")
            payload = {"units": {"records": [{"code": "C62"}]}}
            with (
                patch.object(generator, "RESOURCES", Path(directory)),
                patch.object(generator, "build", return_value=payload),
                patch.object(sys, "argv", ["generate_catalogs.py", "--check"]),
                redirect_stdout(StringIO()),
            ):
                with self.assertRaisesRegex(
                    ValueError, "Catalog differs from sources: units"
                ):
                    generator.main()
            self.assertEqual((catalog_directory / "units.json").read_bytes(), b"stale")

    def test_six_committed_catalogs_match_generated_bytes(self) -> None:
        expected_names = {
            "units",
            "countries",
            "locations",
            "currencies",
            "payment_means",
            "tax_exemption_reasons",
        }
        built = generator.build()
        self.assertEqual(set(built), expected_names)
        for name, payload in built.items():
            content = (
                json.dumps(
                    {"schema_version": 1, "count": len(payload["records"]), **payload},
                    ensure_ascii=False,
                    sort_keys=True,
                    indent=2,
                )
                + "\n"
            ).encode()
            self.assertEqual(
                (generator.RESOURCES / "catalogs" / f"{name}.json").read_bytes(),
                content,
                name,
            )


if __name__ == "__main__":
    unittest.main()
